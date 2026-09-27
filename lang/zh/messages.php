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
        'suspended' => '公司已暂停使用。',
        'activated' => '公司已重新启用。',
    ],

    'role' => [
        'created' => '角色创建成功。',
        'updated' => '角色更新成功。',
        'deleted' => '角色删除成功。',
        'cannot_delete_locked' => '角色 ":name" 无法删除。',
        'cannot_edit_system' => '系统角色由应用程序管理，无法更改。',
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

    'uom' => [
        'created' => '计量单位创建成功。',
        'updated' => '计量单位更新成功。',
        'deleted' => '计量单位删除成功。',
        'cannot_delete_in_use' => '该计量单位仍被产品使用，无法删除。',
    ],

    'warehouse' => [
        'created' => '仓库创建成功。',
        'updated' => '仓库更新成功。',
        'deleted' => '仓库删除成功。',
        'cannot_delete_in_use' => '该仓库仍被产品使用，无法删除。',
    ],

    'tax_code' => [
        'created' => '税码创建成功。',
        'updated' => '税码更新成功。',
        'deleted' => '税码删除成功。',
    ],

    'supplier' => [
        'created' => '供应商创建成功。',
        'updated' => '供应商更新成功。',
        'deleted' => '供应商删除成功。',
    ],

    'customer' => [
        'created' => '客户创建成功。',
        'updated' => '客户更新成功。',
        'deleted' => '客户删除成功。',
    ],

    'product_category' => [
        'created' => '产品类别创建成功。',
        'updated' => '产品类别更新成功。',
        'deleted' => '产品类别删除成功。',
        'cannot_delete_in_use' => '该类别仍被产品使用，无法删除。',
    ],

    'product' => [
        'created' => '产品创建成功。',
        'updated' => '产品更新成功。',
        'deleted' => '产品删除成功。',
    ],

    'account' => [
        'created' => '科目创建成功。',
        'updated' => '科目更新成功。',
        'deleted' => '科目删除成功。',
        'cannot_be_own_parent' => '科目不能将自己设为上级科目。',
        'cannot_delete_has_children' => '该科目仍有下级科目，无法删除。',
        'cannot_delete_in_use' => '该科目已有分录记录，无法删除。',
    ],

    'stock_opname' => [
        'created' => '库存盘点单创建成功。',
        'lines_saved' => '盘点结果保存成功。',
        'completed' => '库存盘点已完成，库存已调整。',
        'not_draft' => '该盘点单已完成，无法再修改。',
        'no_counted_lines' => '尚未填写任何盘点数量。',
        'adjustment_note' => '来自库存盘点 :number 的调整',
    ],

    'work_center' => [
        'created' => '工作中心创建成功。',
        'updated' => '工作中心更新成功。',
        'deleted' => '工作中心删除成功。',
        'cannot_delete_in_use' => '该工作中心仍在工艺路线中使用，无法删除。',
    ],

    'bom' => [
        'created' => '物料清单创建成功。',
        'updated' => '物料清单更新成功。',
        'deleted' => '物料清单删除成功。',
        'cannot_delete_in_use' => '该物料清单正被产品使用中，无法删除。',
        'cannot_edit_immutable' => '已批准或已启用的 BOM 不可编辑，请创建新修订版本。',
        'cannot_delete_immutable' => '已批准或已启用的 BOM 不可删除。',
        'new_version_created' => 'BOM 修订草稿已创建。',
    ],

    'routing' => [
        'created' => '工艺路线创建成功。',
        'updated' => '工艺路线更新成功。',
        'deleted' => '工艺路线删除成功。',
        'cannot_delete_in_use' => '该工艺路线正被产品使用中，无法删除。',
    ],

    'production_order' => [
        'created' => '生产工单创建成功。',
        'missing_bom_routing' => '该产品尚未配置有效的物料清单和工艺路线。',
        'released' => '生产工单已下达。',
        'materials_issued' => '物料已成功发放用于生产。',
        'operation_started' => '工序已开始。',
        'operation_completed' => '工序已完成。',
        'completed' => '生产工单已完成，成品已入库。',
        'submitted_for_qc' => '生产工单已提交质检。',
        'costed' => '生产工单成本计算已完成。',
    ],

    'quality' => [
        'order_not_pending_qc' => '该生产工单尚未处于待质检状态。',
        'passed_exceeds_inspected' => '合格数量不能超过检验数量。',
        'final_inspection_recorded' => '终检记录成功，成品已根据质检结果处理。',
        'final_inspection_already_recorded' => '该生产工单已记录终检。',
        'in_process_inspection_recorded' => '过程检验记录成功。',
        'released' => '质量放行完成。已批准库存现在可按销售规则使用。',
    ],

    'ncr' => [
        'disposition_recorded' => '不合格处置意见记录成功。',
        'corrective_action_recorded' => '纠正措施记录成功。',
        'closed' => '不合格报告已关闭。',
        'rework_order_created' => '已从不合格报告创建返工生产工单。',
    ],

    'purchase_request' => [
        'created' => '请购单创建成功。',
        'submitted' => '请购单已提交审批。',
        'approved' => '请购单审批通过。',
        'rejected' => '请购单已被拒绝。',
    ],

    'purchase_order' => [
        'created' => '已根据请购单创建采购订单。',
        'submitted_for_approval' => '采购订单已提交审批。',
        'approved' => '采购订单审批通过。',
        'sent' => '采购订单已发送给供应商。',
        'closed' => '采购订单已关闭。',
    ],

    'goods_receipt' => [
        'created' => '收货记录成功，等待质量检验。',
        'put_away' => '质量检验完成，合格物料已入库。',
    ],

    'supplier_invoice' => [
        'created' => '供应商发票记录成功。',
    ],

    'sales_order' => [
        'created' => '销售订单创建成功。',
        'submitted_for_approval' => '销售订单已提交审批。',
        'approved' => '销售订单审批通过。',
        'closed' => '销售订单已关闭。',
        'inactive_product' => '已停用的产品不能用于新的销售订单。',
    ],

    'delivery_order' => [
        'created' => '发货单创建成功。',
        'shipped' => '发货成功，库存与成本分录已过账。',
        'cancelled' => '发货单已取消，库存预留已释放。',
    ],

    'sales_invoice' => [
        'created' => '销售发票创建并过账成功。',
    ],

    'sales_return' => [
        'created' => '销售退货记录成功，库存与分录已调整。',
    ],

    'bank_account' => [
        'created' => '现金/银行账户创建成功。',
        'updated' => '现金/银行账户更新成功。',
    ],

    'cash_transaction' => [
        'created' => '现金交易记录成功。',
    ],

    'bank_reconciliation' => [
        'created' => '银行对账创建成功。',
        'lines_updated' => '核对状态更新成功。',
        'completed' => '银行对账已完成。',
    ],

    'ar_receipt' => [
        'created' => '应收账款收款记录成功。',
    ],

    'ap_payment' => [
        'created' => '应付账款付款记录成功。',
    ],

    'fixed_asset' => [
        'created' => '固定资产已成功登记为草稿。',
        'activated' => '固定资产资本化成功。',
        'depreciated' => '固定资产折旧已成功过账。',
        'disposed' => '固定资产处置已成功过账。',
    ],

    'currency' => [
        'enabled' => '已为公司启用货币。',
        'disabled' => '货币已停用。',
        'rate_saved' => '汇率已保存并批准。',
        'base_always_active' => '公司的本位币始终保持启用。',
        'enable_both_first' => '保存汇率前请先启用两种货币。',
        'revaluation_completed' => '货币重估已完成。',
        'revaluation_reversed' => '货币重估已冲销。',
    ],

    'company' => [
        'currency_locked' => '公司已有日记账后不能更改本位币。',
    ],
];
