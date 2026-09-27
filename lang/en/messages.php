<?php

return [
    'company' => [
        'created' => 'Company created successfully.',
        'updated' => 'Company updated successfully.',
        'switched' => 'Company switched successfully.',
        'not_member' => 'You are not a member of that company.',
        'deleted' => 'Company deleted successfully.',
        'has_dependent_data' => 'This company cannot be deleted because it still has related business data.',
        'cannot_delete_only_company' => 'You cannot delete your only company.',
        'suspended' => 'The company has been suspended.',
        'activated' => 'The company has been activated again.',
    ],

    'role' => [
        'created' => 'Role created successfully.',
        'updated' => 'Role updated successfully.',
        'deleted' => 'Role deleted successfully.',
        'cannot_delete_locked' => 'Role ":name" cannot be deleted.',
        'cannot_edit_system' => 'System roles are managed by the application and cannot be changed.',
        'cannot_delete_in_use' => 'This role cannot be deleted because it is still used by :count company membership(s).',
    ],

    'user' => [
        'created' => 'User created successfully.',
        'invited' => 'User added to this company successfully.',
        'updated' => 'User updated successfully.',
        'role_updated' => 'User role updated successfully.',
        'removed' => 'User removed from company successfully.',
        'not_member' => 'That user is not a member of this company.',
        'cannot_remove_self' => 'You cannot remove your own account.',
        'cannot_remove_last_admin' => 'Cannot remove the last admin of this company.',
    ],

    'uom' => [
        'created' => 'Unit of measure created successfully.',
        'updated' => 'Unit of measure updated successfully.',
        'deleted' => 'Unit of measure deleted successfully.',
        'cannot_delete_in_use' => 'This unit of measure cannot be deleted because it is still used by a product.',
    ],

    'warehouse' => [
        'created' => 'Warehouse created successfully.',
        'updated' => 'Warehouse updated successfully.',
        'deleted' => 'Warehouse deleted successfully.',
        'cannot_delete_in_use' => 'This warehouse cannot be deleted because it is still used by a product.',
    ],

    'tax_code' => [
        'created' => 'Tax code created successfully.',
        'updated' => 'Tax code updated successfully.',
        'deleted' => 'Tax code deleted successfully.',
    ],

    'supplier' => [
        'created' => 'Supplier created successfully.',
        'updated' => 'Supplier updated successfully.',
        'deleted' => 'Supplier deleted successfully.',
    ],

    'customer' => [
        'created' => 'Customer created successfully.',
        'updated' => 'Customer updated successfully.',
        'deleted' => 'Customer deleted successfully.',
    ],

    'product_category' => [
        'created' => 'Product category created successfully.',
        'updated' => 'Product category updated successfully.',
        'deleted' => 'Product category deleted successfully.',
        'cannot_delete_in_use' => 'This category cannot be deleted because it is still used by a product.',
    ],

    'product' => [
        'created' => 'Product created successfully.',
        'updated' => 'Product updated successfully.',
        'deleted' => 'Product deleted successfully.',
    ],

    'account' => [
        'created' => 'Account created successfully.',
        'updated' => 'Account updated successfully.',
        'deleted' => 'Account deleted successfully.',
        'cannot_be_own_parent' => 'An account cannot be its own parent.',
        'cannot_delete_has_children' => 'This account cannot be deleted because it still has sub-accounts.',
        'cannot_delete_in_use' => 'This account cannot be deleted because it already has journal transactions.',
    ],

    'stock_opname' => [
        'created' => 'Stock opname created successfully.',
        'lines_saved' => 'Physical count results saved successfully.',
        'completed' => 'Stock opname completed, stock has been adjusted.',
        'not_draft' => 'This stock opname is already completed and can no longer be changed.',
        'no_counted_lines' => 'No lines have a counted quantity yet.',
        'adjustment_note' => 'Adjustment from stock opname :number',
    ],

    'work_center' => [
        'created' => 'Work center created successfully.',
        'updated' => 'Work center updated successfully.',
        'deleted' => 'Work center deleted successfully.',
        'cannot_delete_in_use' => 'This work center cannot be deleted because it is still used in a routing.',
    ],

    'bom' => [
        'created' => 'BOM created successfully.',
        'updated' => 'BOM updated successfully.',
        'deleted' => 'BOM deleted successfully.',
        'cannot_delete_in_use' => 'This BOM cannot be deleted because it is actively used by a product.',
        'cannot_edit_immutable' => 'An approved or active BOM cannot be edited. Create a new revision instead.',
        'cannot_delete_immutable' => 'An approved or active BOM cannot be deleted.',
        'new_version_created' => 'BOM revision draft created successfully.',
    ],

    'routing' => [
        'created' => 'Routing created successfully.',
        'updated' => 'Routing updated successfully.',
        'deleted' => 'Routing deleted successfully.',
        'cannot_delete_in_use' => 'This routing cannot be deleted because it is actively used by a product.',
    ],

    'production_order' => [
        'created' => 'Production order created successfully.',
        'missing_bom_routing' => 'This product does not have an active BOM and Routing yet.',
        'released' => 'Production order released successfully.',
        'materials_issued' => 'Materials issued for production successfully.',
        'operation_started' => 'Operation started successfully.',
        'operation_completed' => 'Operation completed successfully.',
        'completed' => 'Production order completed, finished goods added to stock.',
        'submitted_for_qc' => 'Production order submitted for QC inspection successfully.',
        'costed' => 'Production order costing calculated successfully.',
    ],

    'quality' => [
        'order_not_pending_qc' => 'This production order is not pending QC yet.',
        'passed_exceeds_inspected' => 'Passed quantity cannot exceed the inspected quantity.',
        'final_inspection_recorded' => 'Final inspection recorded, finished goods processed according to the QC result.',
        'final_inspection_already_recorded' => 'A Final inspection has already been recorded for this production order.',
        'in_process_inspection_recorded' => 'In-process inspection recorded successfully.',
        'released' => 'Quality Release completed. Approved stock is now available under sales rules.',
    ],

    'ncr' => [
        'disposition_recorded' => 'NCR disposition recorded successfully.',
        'corrective_action_recorded' => 'Corrective action recorded successfully.',
        'closed' => 'NCR closed successfully.',
        'rework_order_created' => 'A rework production order was created from the NCR.',
    ],

    'purchase_request' => [
        'created' => 'Purchase request created successfully.',
        'submitted' => 'Purchase request submitted for approval successfully.',
        'approved' => 'Purchase request approved successfully.',
        'rejected' => 'Purchase request rejected.',
    ],

    'purchase_order' => [
        'created' => 'Purchase order created from the purchase request successfully.',
        'submitted_for_approval' => 'Purchase order submitted for approval successfully.',
        'approved' => 'Purchase order approved successfully.',
        'sent' => 'Purchase order sent to the supplier successfully.',
        'closed' => 'Purchase order closed successfully.',
    ],

    'goods_receipt' => [
        'created' => 'Goods receipt recorded successfully, pending quality inspection.',
        'put_away' => 'Quality inspection complete, accepted goods added to stock.',
    ],

    'supplier_invoice' => [
        'created' => 'Supplier invoice recorded successfully.',
    ],

    'sales_order' => [
        'created' => 'Sales order created successfully.',
        'submitted_for_approval' => 'Sales order submitted for approval successfully.',
        'approved' => 'Sales order approved successfully.',
        'closed' => 'Sales order closed successfully.',
        'inactive_product' => 'An inactive product cannot be used on a new sales order.',
    ],

    'delivery_order' => [
        'created' => 'Delivery order created successfully.',
        'shipped' => 'Goods shipped successfully, stock and COGS journal posted.',
        'cancelled' => 'Delivery order cancelled and the stock reservation released.',
    ],

    'sales_invoice' => [
        'created' => 'Sales invoice created and posted successfully.',
    ],

    'sales_return' => [
        'created' => 'Sales return recorded successfully, stock and journal adjusted.',
    ],

    'bank_account' => [
        'created' => 'Bank account created successfully.',
        'updated' => 'Bank account updated successfully.',
    ],

    'cash_transaction' => [
        'created' => 'Cash transaction recorded successfully.',
    ],

    'bank_reconciliation' => [
        'created' => 'Bank reconciliation created successfully.',
        'lines_updated' => 'Cleared status updated successfully.',
        'completed' => 'Bank reconciliation completed successfully.',
    ],

    'ar_receipt' => [
        'created' => 'AR receipt recorded successfully.',
    ],

    'ap_payment' => [
        'created' => 'AP payment recorded successfully.',
    ],

    'fixed_asset' => [
        'created' => 'Fixed asset registered as a draft successfully.',
        'activated' => 'Fixed asset capitalized successfully.',
        'depreciated' => 'Fixed asset depreciation posted successfully.',
        'disposed' => 'Fixed asset disposal posted successfully.',
    ],

    'currency' => [
        'enabled' => 'Currency enabled for the company.',
        'disabled' => 'Currency disabled.',
        'rate_saved' => 'Exchange rate saved and approved.',
        'base_always_active' => 'The company base currency is always active.',
        'enable_both_first' => 'Enable both currencies before saving a rate.',
        'revaluation_completed' => 'Currency revaluation completed.',
        'revaluation_reversed' => 'Currency revaluation reversed.',
    ],

    'company' => [
        'currency_locked' => 'The base currency cannot change after the company has journal entries.',
    ],
];
