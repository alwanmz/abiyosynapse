<?php

return [
    'company' => [
        'created' => '회사가 생성되었습니다.',
        'updated' => '회사 정보가 수정되었습니다.',
        'switched' => '회사가 전환되었습니다.',
        'not_member' => '해당 회사의 구성원이 아닙니다.',
        'deleted' => '회사가 삭제되었습니다.',
        'has_dependent_data' => '관련된 업무 데이터가 남아 있어 이 회사를 삭제할 수 없습니다.',
        'cannot_delete_only_company' => '유일한 회사는 삭제할 수 없습니다.',
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
        'invited' => '사용자가 이 회사에 추가되었습니다.',
        'updated' => '사용자 정보가 수정되었습니다.',
        'role_updated' => '사용자 역할이 수정되었습니다.',
        'removed' => '사용자가 회사에서 제거되었습니다.',
        'not_member' => '해당 사용자는 이 회사의 구성원이 아닙니다.',
        'cannot_remove_self' => '본인 계정은 삭제할 수 없습니다.',
        'cannot_remove_last_admin' => '이 회사의 마지막 관리자는 제거할 수 없습니다.',
    ],

    'uom' => [
        'created' => '단위가 생성되었습니다.',
        'updated' => '단위가 수정되었습니다.',
        'deleted' => '단위가 삭제되었습니다.',
        'cannot_delete_in_use' => '이 단위는 제품에서 사용 중이므로 삭제할 수 없습니다.',
    ],

    'warehouse' => [
        'created' => '창고가 생성되었습니다.',
        'updated' => '창고가 수정되었습니다.',
        'deleted' => '창고가 삭제되었습니다.',
        'cannot_delete_in_use' => '이 창고는 제품에서 사용 중이므로 삭제할 수 없습니다.',
    ],

    'tax_code' => [
        'created' => '세금 코드가 생성되었습니다.',
        'updated' => '세금 코드가 수정되었습니다.',
        'deleted' => '세금 코드가 삭제되었습니다.',
    ],

    'supplier' => [
        'created' => '공급업체가 생성되었습니다.',
        'updated' => '공급업체가 수정되었습니다.',
        'deleted' => '공급업체가 삭제되었습니다.',
    ],

    'customer' => [
        'created' => '고객이 생성되었습니다.',
        'updated' => '고객이 수정되었습니다.',
        'deleted' => '고객이 삭제되었습니다.',
    ],

    'product_category' => [
        'created' => '제품 카테고리가 생성되었습니다.',
        'updated' => '제품 카테고리가 수정되었습니다.',
        'deleted' => '제품 카테고리가 삭제되었습니다.',
        'cannot_delete_in_use' => '이 카테고리는 제품에서 사용 중이므로 삭제할 수 없습니다.',
    ],

    'product' => [
        'created' => '제품이 생성되었습니다.',
        'updated' => '제품이 수정되었습니다.',
        'deleted' => '제품이 삭제되었습니다.',
    ],

    'account' => [
        'created' => '계정이 생성되었습니다.',
        'updated' => '계정이 수정되었습니다.',
        'deleted' => '계정이 삭제되었습니다.',
        'cannot_be_own_parent' => '계정은 자기 자신을 상위 계정으로 지정할 수 없습니다.',
        'cannot_delete_has_children' => '이 계정은 하위 계정이 있어 삭제할 수 없습니다.',
        'cannot_delete_in_use' => '이 계정은 이미 전표 거래가 있어 삭제할 수 없습니다.',
    ],

    'stock_opname' => [
        'created' => '재고 실사가 생성되었습니다.',
        'lines_saved' => '실사 결과가 저장되었습니다.',
        'completed' => '재고 실사가 완료되어 재고가 조정되었습니다.',
        'not_draft' => '이 재고 실사는 이미 완료되어 더 이상 변경할 수 없습니다.',
        'no_counted_lines' => '아직 실사 수량이 입력된 항목이 없습니다.',
        'adjustment_note' => '재고 실사 :number 에 의한 조정',
    ],

    'work_center' => [
        'created' => '작업장이 생성되었습니다.',
        'updated' => '작업장이 수정되었습니다.',
        'deleted' => '작업장이 삭제되었습니다.',
        'cannot_delete_in_use' => '이 작업장은 라우팅에서 사용 중이므로 삭제할 수 없습니다.',
    ],

    'bom' => [
        'created' => 'BOM이 생성되었습니다.',
        'updated' => 'BOM이 수정되었습니다.',
        'deleted' => 'BOM이 삭제되었습니다.',
        'cannot_delete_in_use' => '이 BOM은 제품에서 활성 사용 중이므로 삭제할 수 없습니다.',
    ],

    'routing' => [
        'created' => '라우팅이 생성되었습니다.',
        'updated' => '라우팅이 수정되었습니다.',
        'deleted' => '라우팅이 삭제되었습니다.',
        'cannot_delete_in_use' => '이 라우팅은 제품에서 활성 사용 중이므로 삭제할 수 없습니다.',
    ],

    'production_order' => [
        'created' => '제조 지시서가 생성되었습니다.',
        'missing_bom_routing' => '이 제품에는 아직 활성 BOM과 라우팅이 없습니다.',
        'released' => '제조 지시서가 발행되었습니다.',
        'materials_issued' => '생산을 위한 자재가 출고되었습니다.',
        'operation_started' => '공정이 시작되었습니다.',
        'operation_completed' => '공정이 완료되었습니다.',
        'completed' => '제조 지시서가 완료되어 완제품이 입고되었습니다.',
        'submitted_for_qc' => '제조 지시서가 품질 검사로 제출되었습니다.',
    ],

    'quality' => [
        'order_not_pending_qc' => '이 제조 지시서는 아직 품질 검사 대기 상태가 아닙니다.',
        'passed_exceeds_inspected' => '합격 수량은 검사 수량을 초과할 수 없습니다.',
        'final_inspection_recorded' => '최종 검사가 기록되었으며, 품질 검사 결과에 따라 완제품이 처리되었습니다.',
    ],

    'ncr' => [
        'disposition_recorded' => '부적합 처리 방침이 기록되었습니다.',
        'corrective_action_recorded' => '시정 조치가 기록되었습니다.',
        'closed' => '부적합 보고서가 종료되었습니다.',
    ],

    'purchase_request' => [
        'created' => '구매 요청이 생성되었습니다.',
        'submitted' => '구매 요청이 승인 요청되었습니다.',
        'approved' => '구매 요청이 승인되었습니다.',
        'rejected' => '구매 요청이 반려되었습니다.',
    ],

    'purchase_order' => [
        'created' => '구매 요청으로부터 발주서가 생성되었습니다.',
        'submitted_for_approval' => '발주서가 승인 요청되었습니다.',
        'approved' => '발주서가 승인되었습니다.',
        'sent' => '발주서가 공급업체에 전송되었습니다.',
        'closed' => '발주서가 종료되었습니다.',
    ],

    'goods_receipt' => [
        'created' => '입고가 기록되었으며, 품질 검사를 기다리고 있습니다.',
        'put_away' => '품질 검사가 완료되어 합격품이 입고 처리되었습니다.',
    ],

    'supplier_invoice' => [
        'created' => '공급업체 인보이스가 기록되었습니다.',
    ],

    'sales_order' => [
        'created' => '판매 주문이 생성되었습니다.',
        'submitted_for_approval' => '판매 주문이 승인 요청되었습니다.',
        'approved' => '판매 주문이 승인되었습니다.',
        'closed' => '판매 주문이 종료되었습니다.',
    ],

    'delivery_order' => [
        'created' => '출고 지시서가 생성되었습니다.',
        'shipped' => '출고가 완료되어 재고와 매출원가 전표가 반영되었습니다.',
    ],

    'sales_invoice' => [
        'created' => '판매 인보이스가 생성되고 전표에 반영되었습니다.',
    ],

    'sales_return' => [
        'created' => '판매 반품이 기록되었으며, 재고와 전표가 조정되었습니다.',
    ],
];
