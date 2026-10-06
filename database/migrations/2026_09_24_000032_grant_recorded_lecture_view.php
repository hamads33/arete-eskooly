<?php
declare(strict_types=1);
/**
 * Staff roles may watch recorded lectures in the admin area. Uploading and deleting
 * (recorded-lectures.manage) stays with the admin, who can grant it per role on the Roles page.
 */
return static function(PDO $pdo):void{
    $link=$pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.slug=:permission WHERE r.slug=:role');
    foreach(['principal','teacher','management-staff'] as$role)$link->execute(['role'=>$role,'permission'=>'recorded-lectures.view']);
};
