<?php

return [
    'company' => [
        'created' => '公司创建成功。',
        'updated' => '公司信息更新成功。',
        'switched' => '公司切换成功。',
        'not_member' => '您不是该公司的成员。',
        'deleted' => '公司删除成功。',
        'has_dependent_data' => '该公司仍有关联的业务数据，无法删除。',
        'cannot_delete_only_company' => '您无法删除您唯一的公司。',
    ],

    'role' => [
        'created' => '角色创建成功。',
        'updated' => '角色更新成功。',
        'deleted' => '角色删除成功。',
        'cannot_delete_locked' => '角色 ":name" 无法删除。',
        'cannot_delete_in_use' => '该角色仍被 :count 个公司成员使用，无法删除。',
    ],

    'user' => [
        'created' => '用户创建成功。',
        'invited' => '用户已成功加入本公司。',
        'updated' => '用户更新成功。',
        'role_updated' => '用户角色更新成功。',
        'removed' => '用户已从公司移除。',
        'not_member' => '该用户不是本公司的成员。',
        'cannot_remove_self' => '您不能移除自己的账户。',
        'cannot_remove_last_admin' => '无法移除本公司的最后一位管理员。',
    ],
];
