<?php

return [
    'company' => [
        'created' => 'Company created successfully.',
        'updated' => 'Company updated successfully.',
        'switched' => 'Company switched successfully.',
        'not_member' => 'You are not a member of that company.',
    ],

    'role' => [
        'created' => 'Role created successfully.',
        'updated' => 'Role updated successfully.',
        'deleted' => 'Role deleted successfully.',
        'cannot_delete_locked' => 'Role ":name" cannot be deleted.',
        'cannot_delete_in_use' => 'This role cannot be deleted because it is still used by :count company membership(s).',
    ],

    'user' => [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'role_updated' => 'User role updated successfully.',
        'removed' => 'User removed from company successfully.',
        'not_member' => 'That user is not a member of this company.',
        'cannot_remove_self' => 'You cannot remove your own account.',
        'cannot_remove_last_admin' => 'Cannot remove the last admin of this company.',
    ],
];
