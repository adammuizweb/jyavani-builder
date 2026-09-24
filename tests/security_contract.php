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
$plugin = (string)file_get_contents($root . '/plugin.php');

$check(($manifest['requires']['jyavani'] ?? null) === '>=2.3.148', 'manifest requires the shared content-list Core release');
$check(($manifest['version'] ?? null) === '3.3.3' && str_contains($plugin, "const JVB_VERSION = '3.3.3'"), 'manifest and runtime declare the 3.3.3 candidate');
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

$index = (string)file_get_contents($root . '/admin/index.php');
$adminUi = (string)file_get_contents($root . '/admin/_ui.php');
$ajax = (string)file_get_contents($root . '/admin/ajax.php');
$builder = (string)file_get_contents($root . '/admin/builder.php');
$builderJs = (string)file_get_contents($root . '/assets/builder.js');
$frameJs = (string)file_get_contents($root . '/assets/frame.js');
$frameCss = (string)file_get_contents($root . '/assets/frame.css');

$check(str_contains($plugin, "if (!function_exists('csrf_check')) return false"), 'CSRF validation fails closed');
$check(str_contains($plugin, 'jvb_current_user_can_edit_post'), 'Core editor integration intersects workspace and Core content permission');
$check(str_contains($plugin, "if (\$type === 'theme')") && str_contains($plugin, "\$actor['is_site_owner'] === true"), 'theme content remains Site Owner-only');
$check(str_contains($plugin, "'core.' . \$resource . '.' . \$action"), 'content actions map to Core post/page permissions');
$check(str_contains($plugin, 'core.pages.unfiltered_html') && str_contains($plugin, 'core.posts.unfiltered_html'), 'restricted elements use Core unfiltered HTML permissions');
$check(str_contains($plugin, 'jvb_migrate_legacy_permissions'), 'legacy administrator action grants migrate once');
$check(!str_contains($plugin, 'jvb_migrate_v1($pdo, $postId)'), 'public rendering performs no implicit layout migration');
$check(str_contains($plugin, 'function jvb_layout_statuses(')
    && str_contains($plugin, 'SELECT post_id, status FROM jvb_layouts')
    && !str_contains($plugin, 'function jvb_layout_statuses(PDO $pdo, array $postIds): array {\n    $row = jvb_get_layout_row'),
    'bulk layout status API reads bounded metadata without loading cached layout JSON');

$check(str_contains($index, "adiwira_require_permission(\$pdo, 'plugin.jyavani-builder.workspace.access'"), 'dashboard repeats its workspace permission guard');
$check(str_contains($index, "jvb_user_can_content_action(\$pdo, \$uid, \$src, 'read')"), 'duplicate verifies Core source read permission');
$check(str_contains($index, "['type' => \$src['type'], 'created_by' => \$uid], 'create'"), 'duplicate verifies Core target creation permission');
$check(str_contains($index, "jvb_user_can_content_action(\$pdo, \$uid, \$fetched, 'update')"), 'builder shell verifies Core update permission');
$check(str_contains($index, "array_filter(\$posts") && str_contains($index, "'read'"), 'workspace list filters rows through Core read permission');
$check(str_contains($index, "'surface' => 'plugin.jyavani-builder'")
    && str_contains($index, "'content_types' =>")
    && str_contains($index, "apply_filters('post_list_status_expression'")
    && str_contains($index, "apply_filters('post_list_search_condition'")
    && str_contains($index, "apply_filters('post_list_join', '', \$whereSql, \$listContext)")
    && str_contains($index, "apply_filters('post_list_select', '', \$whereSql, \$listContext)")
    && str_contains($index, "apply_filters('post_list_rows', \$posts, \$listContext)")
    && str_contains($index, "do_action('admin_content_list_filters', \$listContext, \$pdo)"),
    'workspace list consumes the generic content-list extension surface');
$check(substr_count($plugin, "(\$context['surface'] ?? '') === 'plugin.jyavani-builder'") === 2,
    'Builder list filters skip the internal surface to avoid duplicate layout joins and selects');
$check(str_contains($index, 'get_page_permalink($p)') && str_contains($index, 'get_post_permalink($p)'),
    'workspace View links use locale-aware Core permalink helpers');
$check(str_contains($index, '$listExtensionQuery')
    && str_contains($index, "in_array(\$key, ['page', 'view', 'q', 'type', 'p'], true)")
    && str_contains($index, "\$allowedTypeFilters = \$isSiteOwner ? ['page', 'article', 'theme'] : ['page', 'article']")
    && substr_count($index, 'array_merge($listExtensionQuery') === 4
    && str_contains($index, '>Theme Content</a>'),
    'workspace type toggles include Site Owner Theme Content and retain bounded extension filters');
$check(!str_contains($index, "value=\"set_home\"") && !str_contains($index, "value=\"unset_home\"")
    && !str_contains($index, 'Set home'),
    'workspace removes homepage designation actions');
$check(str_contains($index, 'class="jvba-overflow-trigger"')
    && str_contains($index, 'aria-haspopup="menu"')
    && str_contains($index, 'role="menuitem"')
    && str_contains($index, 'event.key === \'Escape\'')
    && str_contains($index, 'menu.querySelector(\'[role="menuitem"]\')?.focus()')
    && str_contains($index, 'document.body.appendChild(menu)')
    && str_contains($index, "window.addEventListener('scroll', () => close(false), true)"),
    'workspace exposes only an accessible clipping-safe overflow trigger for row actions');
$check(str_contains($index, 'class="jvba-updated"')
    && str_contains($index, 'app_display_date((string)$p[\'updated_at\'])')
    && !str_contains($index, '<td class="jvba-sub"')
    && str_contains($adminUi, 'vertical-align: middle')
    && str_contains($adminUi, '.jvba-updated { white-space: nowrap; }')
    && str_contains($adminUi, '.jvba-overflow-menu { position: fixed;'),
    'Updated remains a table cell and uses the configured Core date format');
$check(str_contains($index, '$listPerPage = 15')
    && str_contains($index, '$listPages = max(1, (int)ceil($listTotal / $listPerPage))')
    && str_contains($index, 'array_slice($posts, ($listPage - 1) * $listPerPage, $listPerPage)')
    && str_contains($index, '$listPagingItems')
    && str_contains($index, "['page', 'view', 'q', 'type', 'p']")
    && str_contains($index, 'adam-pagination pagination-wrap')
    && str_contains($index, 'aria-current="page"'),
    'workspace list paginates fifteen authorized rows and preserves validated filters');
$check(str_contains($adminUi, '.jvba-overflow-menu a:hover, .jvba-overflow-menu a:focus-visible { text-decoration: none !important; }'),
    'workspace link actions suppress inherited dashboard underlines');
$check(str_contains($builder, "'change_owner'") && str_contains($builder, '$canChangeOwner'), 'author selector requires both plugin elevation and Core change-owner permission');

$check(str_contains($ajax, "!== 'POST'") && str_contains($ajax, 'jvb_csrf_ok()'), 'JSON mutations require POST and CSRF');
$check(str_contains($ajax, "jvb_require_content_action(\$pdo, \$uid, \$post, 'publish')"), 'publish and unpublish intersect Core publish permission');
$check(str_contains($ajax, "'change_owner'"), 'owner changes intersect Core change-owner permission');
$check(str_contains($ajax, '$storedDraft') && str_contains($ajax, 'jvb_layout_has_restricted_elements($storedDraft)'), 'publishing validates stored draft restrictions');
$check(str_contains($ajax, '$revisionLayout') && str_contains($ajax, 'jvb_layout_has_restricted_elements($revisionLayout)'), 'revision restore validates stored restrictions');
$check(str_contains($ajax, '$templateLayout') && str_contains($ajax, 'jvb_layout_has_restricted_elements($templateLayout)'), 'template retrieval hides restricted layouts');
$check(!preg_match("/===\\s*'admin'|!==\\s*'admin'/", $ajax), 'AJAX authorization has no direct legacy-admin bypass');
$check(str_contains($builderJs, 'post_id: S.postId') && str_contains($builderJs, 'post_type:'), 'frame stash sends its content resource context');
$check(str_contains($ajax, "require \$layoutPath")
    && str_contains($ajax, "add_filter('layout_slot_html'")
    && str_contains($ajax, "add_action('jy_head'")
    && str_contains($ajax, "add_action('jy_footer'")
    && !str_contains($ajax, '<!DOCTYPE html>')
    && str_contains($ajax, 'Cache-Control: private, no-store')
    && str_contains($ajax, 'X-Robots-Tag: noindex, nofollow'),
    'canvas frame renders through the Core public layout with private preview headers');
$check(str_contains($ajax, "'schema' => 1")
    && str_contains($ajax, "'uid' => \$uid")
    && str_contains($ajax, "'post_id' => \$postId")
    && str_contains($ajax, "random_bytes(16)")
    && str_contains($ajax, "time() - 300")
    && str_contains($ajax, "count(\$_SESSION['jvb_frame']) >= 8"),
    'frame stash is bounded, expiring, and bound to the authenticated user and post');
$check(str_contains($frameCss, 'body > :not(#site-main):not(script)')
    && str_contains($frameCss, 'pointer-events: none !important')
    && str_contains($frameJs, 'element.inert = true')
    && str_contains($ajax, 'opacity: .34 !important')
    && str_contains($ajax, "document.addEventListener('DOMContentLoaded'")
    && str_contains($ajax, "document.addEventListener('click'")
    && str_contains($ajax, "document.addEventListener('submit'"),
    'public header and footer remain visible but muted and noninteractive in the canvas');
$check(str_contains($builderJs, "window.location.origin")
    && str_contains($builderJs, 'e.source !== frame.contentWindow')
    && str_contains($frameJs, "window.location.origin")
    && str_contains($frameJs, 'e.source !== window.parent')
    && !str_contains($frameJs, "msg), '*'")
    && !str_contains($builderJs, "msg), '*'"),
    'canvas messaging binds the exact same-origin parent and frame windows');

if ($failures !== []) {
    fwrite(STDERR, 'Jy Builder security contract failed: ' . implode('; ', array_unique($failures)) . "\n");
    exit(1);
}

echo "RESULT: ALL PASS\n";
