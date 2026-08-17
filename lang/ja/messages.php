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

    'uom' => [
        'created' => '単位を作成しました。',
        'updated' => '単位を更新しました。',
        'deleted' => '単位を削除しました。',
        'cannot_delete_in_use' => 'この単位は製品で使用されているため削除できません。',
    ],

    'warehouse' => [
        'created' => '倉庫を作成しました。',
        'updated' => '倉庫を更新しました。',
        'deleted' => '倉庫を削除しました。',
        'cannot_delete_in_use' => 'この倉庫は製品で使用されているため削除できません。',
    ],

    'tax_code' => [
        'created' => '税区分を作成しました。',
        'updated' => '税区分を更新しました。',
        'deleted' => '税区分を削除しました。',
    ],

    'supplier' => [
        'created' => '仕入先を作成しました。',
        'updated' => '仕入先を更新しました。',
        'deleted' => '仕入先を削除しました。',
    ],

    'customer' => [
        'created' => '得意先を作成しました。',
        'updated' => '得意先を更新しました。',
        'deleted' => '得意先を削除しました。',
    ],

    'product_category' => [
        'created' => '製品カテゴリーを作成しました。',
        'updated' => '製品カテゴリーを更新しました。',
        'deleted' => '製品カテゴリーを削除しました。',
        'cannot_delete_in_use' => 'このカテゴリーは製品で使用されているため削除できません。',
    ],

    'product' => [
        'created' => '製品を作成しました。',
        'updated' => '製品を更新しました。',
        'deleted' => '製品を削除しました。',
    ],

    'account' => [
        'created' => '勘定科目を作成しました。',
        'updated' => '勘定科目を更新しました。',
        'deleted' => '勘定科目を削除しました。',
        'cannot_be_own_parent' => '勘定科目は自分自身を親科目にできません。',
        'cannot_delete_has_children' => 'この勘定科目は下位科目があるため削除できません。',
        'cannot_delete_in_use' => 'この勘定科目はすでに仕訳が存在するため削除できません。',
    ],

    'stock_opname' => [
        'created' => '棚卸を作成しました。',
        'lines_saved' => '実地棚卸の結果を保存しました。',
        'completed' => '棚卸が完了し、在庫が調整されました。',
        'not_draft' => 'この棚卸はすでに完了しているため変更できません。',
        'no_counted_lines' => '実地数量が入力された行がまだありません。',
        'adjustment_note' => '棚卸 :number による調整',
    ],

    'work_center' => [
        'created' => 'ワークセンターを作成しました。',
        'updated' => 'ワークセンターを更新しました。',
        'deleted' => 'ワークセンターを削除しました。',
        'cannot_delete_in_use' => 'このワークセンターはルーティングで使用されているため削除できません。',
    ],

    'bom' => [
        'created' => 'BOMを作成しました。',
        'updated' => 'BOMを更新しました。',
        'deleted' => 'BOMを削除しました。',
        'cannot_delete_in_use' => 'このBOMは製品で有効に使用されているため削除できません。',
    ],

    'routing' => [
        'created' => 'ルーティングを作成しました。',
        'updated' => 'ルーティングを更新しました。',
        'deleted' => 'ルーティングを削除しました。',
        'cannot_delete_in_use' => 'このルーティングは製品で有効に使用されているため削除できません。',
    ],

    'production_order' => [
        'created' => '製造指図を作成しました。',
        'missing_bom_routing' => 'この製品にはまだ有効なBOMとルーティングが設定されていません。',
        'released' => '製造指図を発行しました。',
        'materials_issued' => '生産用の材料を出庫しました。',
        'operation_started' => '工程を開始しました。',
        'operation_completed' => '工程を完了しました。',
        'completed' => '製造指図が完了し、完成品が入庫されました。',
        'submitted_for_qc' => '製造指図をQC検査に提出しました。',
    ],

    'quality' => [
        'order_not_pending_qc' => 'この製造指図はまだQC待ちの状態ではありません。',
        'passed_exceeds_inspected' => '合格数量は検査数量を超えることはできません。',
        'final_inspection_recorded' => '最終検査を記録しました。QC結果に応じて完成品が処理されました。',
    ],

    'ncr' => [
        'disposition_recorded' => '不適合処置を記録しました。',
        'corrective_action_recorded' => '是正措置を記録しました。',
        'closed' => '不適合報告書をクローズしました。',
    ],

    'purchase_request' => [
        'created' => '購買依頼を作成しました。',
        'submitted' => '購買依頼を承認申請しました。',
        'approved' => '購買依頼が承認されました。',
        'rejected' => '購買依頼が却下されました。',
    ],

    'purchase_order' => [
        'created' => '購買依頼から発注書を作成しました。',
        'submitted_for_approval' => '発注書を承認申請しました。',
        'approved' => '発注書が承認されました。',
        'sent' => '発注書をサプライヤーに送信しました。',
        'closed' => '発注書をクローズしました。',
    ],

    'goods_receipt' => [
        'created' => '入荷を記録しました。品質検査待ちです。',
        'put_away' => '品質検査が完了し、合格品を入庫しました。',
    ],

    'supplier_invoice' => [
        'created' => 'サプライヤー請求書を記録しました。',
    ],

    'sales_order' => [
        'created' => '受注を作成しました。',
        'submitted_for_approval' => '受注を承認申請しました。',
        'approved' => '受注が承認されました。',
        'closed' => '受注をクローズしました。',
    ],

    'delivery_order' => [
        'created' => '出荷指図を作成しました。',
        'shipped' => '出荷が完了し、在庫と原価の仕訳が計上されました。',
    ],

    'sales_invoice' => [
        'created' => '売上請求書を作成し、計上しました。',
    ],

    'sales_return' => [
        'created' => '返品を記録しました。在庫と仕訳が調整されました。',
    ],
];
