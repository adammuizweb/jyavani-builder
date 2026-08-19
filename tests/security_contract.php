<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "PASS {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL {$message}\n";
};

try {
    $manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $manifest = [];
    $failures[] = 'plugin.json parses';
}

$check(($manifest['requires']['jyavani'] ?? null) === '>=2.3.74', 'manifest requires the default_roles/delegable Core release');
$permissionKeys = array_column($manifest['permissions'] ?? [], 'key');
sort($permissionKeys);
$expectedKeys = [
    'plugin.jyavani-builder.content.manage-any',
    'plugin.jyavani-builder.site-settings.manage',
    'plugin.jyavani-builder.workspace.access',
];
$check($permissionKeys === $expectedKeys, 'manifest declares the complete Jy Builder permission family');
$permissionDefaults = [];
foreach ($manifest['permissions'] ?? [] as $permission) {
    $permissionDefaults[$permission['key'] ?? ''] = $permission['default_roles'] ?? null;
}
$check(($permissionDefaults['plugin.jyavani-builder.workspace.access'] ?? null) === ['author', 'editor', 'admin'], 'workspace defaults to author, editor, and admin');
$check(($permissionDefaults['plugin.jyavani-builder.content.manage-any'] ?? null) === ['admin'], 'manage-any defaults to admin');
$check(($permissionDefaults['plugin.jyavani-builder.site-settings.manage'] ?? null) === ['admin'], 'site settings defaults to admin');
$pages = $manifest['admin']['pages'] ?? [];
$check(count($pages) === 1, 'manifest declares one dashboard route');
$check(($pages[0]['permission'] ?? null) === 'plugin.jyavani-builder.workspace.access', 'dashboard route uses workspace permission');

$plugin = (string)file_get_contents($root . '/plugin.php');
$index = (string)file_get_contents($root . '/admin/index.php');
$ajax = (string)file_get_contents($root . '/admin/ajax.php');
$builder = (string)file_get_contents($root . '/admin/builder.php');
$builderJs = (string)file_get_contents($root . '/assets/builder.js');

$check(str_contains($plugin, "if (!function_exists('csrf_check')) return false"), 'CSRF validation fails closed');
$check(str_contains($plugin, 'jvb_current_user_can_edit_post'), 'Core editor integration intersects workspace and Core content permission');
$check(str_contains($plugin, "if (\$type === 'theme')") && str_contains($plugin, "\$actor['is_site_owner'] === true"), 'theme content remains Site Owner-only');
$check(str_contains($plugin, "'core.' . \$resource . '.' . \$action"), 'content actions map to Core post/page permissions');
$check(str_contains($plugin, 'core.pages.unfiltered_html') && str_contains($plugin, 'core.posts.unfiltered_html'), 'restricted elements use Core unfiltered HTML permissions');
$check(str_contains($plugin, 'jvb_migrate_legacy_permissions'), 'legacy administrator action grants migrate once');
$check(!str_contains($plugin, 'jvb_migrate_v1($pdo, $postId)'), 'public rendering performs no implicit layout migration');

$check(str_contains($index, "adiwira_require_permission(\$pdo, 'plugin.jyavani-builder.workspace.access'"), 'dashboard repeats its workspace permission guard');
$check(str_contains($index, "jvb_user_can_content_action(\$pdo, \$uid, \$src, 'read')"), 'duplicate verifies Core source read permission');
$check(str_contains($index, "['type' => \$src['type'], 'created_by' => \$uid], 'create'"), 'duplicate verifies Core target creation permission');
$check(str_contains($index, "jvb_user_can_content_action(\$pdo, \$uid, \$fetched, 'update')"), 'builder shell verifies Core update permission');
$check(str_contains($index, "array_filter(\$posts") && str_contains($index, "'read'"), 'workspace list filters rows through Core read permission');
$check(str_contains($builder, "'change_owner'") && str_contains($builder, '$canChangeOwner'), 'author selector requires both plugin elevation and Core change-owner permission');

$check(str_contains($ajax, "!== 'POST'") && str_contains($ajax, 'jvb_csrf_ok()'), 'JSON mutations require POST and CSRF');
$check(str_contains($ajax, "jvb_require_content_action(\$pdo, \$uid, \$post, 'publish')"), 'publish and unpublish intersect Core publish permission');
$check(str_contains($ajax, "'change_owner'"), 'owner changes intersect Core change-owner permission');
$check(str_contains($ajax, '$storedDraft') && str_contains($ajax, 'jvb_layout_has_restricted_elements($storedDraft)'), 'publishing validates stored draft restrictions');
$check(str_contains($ajax, '$revisionLayout') && str_contains($ajax, 'jvb_layout_has_restricted_elements($revisionLayout)'), 'revision restore validates stored restrictions');
$check(str_contains($ajax, '$templateLayout') && str_contains($ajax, 'jvb_layout_has_restricted_elements($templateLayout)'), 'template retrieval hides restricted layouts');
$check(!preg_match("/===\\s*'admin'|!==\\s*'admin'/", $ajax), 'AJAX authorization has no direct legacy-admin bypass');
$check(str_contains($builderJs, 'post_id: S.postId') && str_contains($builderJs, 'post_type:'), 'frame stash sends its content resource context');

if ($failures !== []) {
    fwrite(STDERR, 'Jy Builder security contract failed: ' . implode('; ', array_unique($failures)) . "\n");
    exit(1);
}

echo "RESULT: ALL PASS\n";
