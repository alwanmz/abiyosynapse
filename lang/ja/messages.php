<?php

return [
    'company' => [
        'created' => '会社を作成しました。',
        'updated' => '会社情報を更新しました。',
        'switched' => '会社を切り替えました。',
        'not_member' => 'その会社のメンバーではありません。',
        'deleted' => '会社を削除しました。',
        'has_dependent_data' => 'この会社には関連する業務データが残っているため削除できません。',
        'cannot_delete_only_company' => '唯一の会社は削除できません。',
    ],

    'role' => [
        'created' => 'ロールを追加しました。',
        'updated' => 'ロールを更新しました。',
        'deleted' => 'ロールを削除しました。',
        'cannot_delete_locked' => 'ロール「:name」は削除できません。',
        'cannot_delete_in_use' => 'このロールは :count 件の会社メンバーシップで使用されているため削除できません。',
    ],

    'user' => [
        'created' => 'ユーザーを作成しました。',
        'invited' => 'ユーザーをこの会社に追加しました。',
        'updated' => 'ユーザー情報を更新しました。',
        'role_updated' => 'ユーザーのロールを更新しました。',
        'removed' => 'ユーザーを会社から削除しました。',
        'not_member' => 'そのユーザーはこの会社のメンバーではありません。',
        'cannot_remove_self' => '自分自身のアカウントは削除できません。',
        'cannot_remove_last_admin' => 'この会社の最後の管理者は削除できません。',
    ],
];
