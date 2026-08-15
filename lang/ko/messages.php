<?php

return [
    'company' => [
        'created' => '회사가 생성되었습니다.',
        'updated' => '회사 정보가 수정되었습니다.',
        'switched' => '회사가 전환되었습니다.',
        'not_member' => '해당 회사의 구성원이 아닙니다.',
    ],

    'role' => [
        'created' => '역할이 추가되었습니다.',
        'updated' => '역할이 수정되었습니다.',
        'deleted' => '역할이 삭제되었습니다.',
        'cannot_delete_locked' => '":name" 역할은 삭제할 수 없습니다.',
        'cannot_delete_in_use' => '이 역할은 :count개의 회사 구성원에서 사용 중이므로 삭제할 수 없습니다.',
    ],

    'user' => [
        'created' => '사용자가 추가되었습니다.',
        'updated' => '사용자 정보가 수정되었습니다.',
        'role_updated' => '사용자 역할이 수정되었습니다.',
        'removed' => '사용자가 회사에서 제거되었습니다.',
        'not_member' => '해당 사용자는 이 회사의 구성원이 아닙니다.',
        'cannot_remove_self' => '본인 계정은 삭제할 수 없습니다.',
        'cannot_remove_last_admin' => '이 회사의 마지막 관리자는 제거할 수 없습니다.',
    ],
];
