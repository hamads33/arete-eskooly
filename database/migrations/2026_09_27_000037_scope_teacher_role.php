<?php
declare(strict_types=1);
/**
 * Intentionally preserves existing teacher grants.
 *
 * Teacher workspace routes enforce class/subject assignment scope in application code. Existing
 * production role permissions may have been granted deliberately, so a schema deployment must not
 * revoke them. Administrators can review or narrow those grants later through Roles & Permissions.
 */
return static function(PDO $pdo):void{
    // No data mutation. Keeping this migration records the deployment without rewriting live RBAC.
};
