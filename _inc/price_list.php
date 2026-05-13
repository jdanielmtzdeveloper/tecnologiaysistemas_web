<?php
ob_start();
session_start();
include("../_init.php");

// Check, if user logged in or not
// If user is not logged in then return an alert message
// if (!is_loggedin()) {
//     header('HTTP/1.1 422 Unprocessable Entity');
//     header('Content-Type: application/json; charset=UTF-8');
//     echo json_encode(array('errorMsg' => trans('error_login')));
//     exit();
// }

$store_id = store_id();
$user_id = user_id();


/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_Transactions_List');

// DB table to use
$table = "(SELECT `pl`.`id_price_list`, `pl`.`name`, `us`.`username`, `pl`.`status`, `pl`.`created_at` FROM `price_list` as `pl` INNER JOIN `users` as `us` ON `us`.`id` = `pl`.`id_user_created`) as price_list;";
// Table's primary key
$primaryKey = 'id_price_list';


$columns = array(
    array(
        'db' => 'id_price_list',
        'dt' => 'DT_RowId',
        'formatter' => function ($d, $row) {
            return 'row_' . $d;
        }
    ),
    array('db' => 'id_price_list', 'dt' => 'id_price_list'),
    array(
        'db' => 'name',
        'dt' => 'name',
        'formatter' => function ($d, $row) {
            return $row['name'];
        }
    ),
    array(
        'db' => 'username',
        'dt' => 'username',
        'formatter' => function ($d, $row) {
            return $row['username'];
        }
    ),
    array(
        'db' => 'created_at',
        'dt' => 'created_at',
        'formatter' => function ($d, $row) {
            return $row['created_at'];
        }
    ),
    array(
        'db' => 'status',
        'dt' => 'status',
        'formatter' => function ($d, $row) {
            return ($row['status'] == 1) ? "ACTIVO" : "INACTIVO";
        }
    ),
    array(
        'db'        => 'id_price_list',
        'dt'        => 'btn_view',
        'formatter' => function ($d, $row) {
            return '<button id="view-transaction-btn" class="btn btn-sm btn-block btn-info" type="button" title="' . trans('button_view') . '"><i class="fa fa-fw fa-eye"></i></button>';
        }
    ),
);

echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_PriceList_List');

/**
 *===================
 * END DATATABLE
 *===================
 */
