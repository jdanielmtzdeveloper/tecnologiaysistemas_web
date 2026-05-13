<?php
ob_start();
session_start();
include("../_init.php");

// Redirect, If user is not logged in
if (!is_loggedin()) {
    redirect(root_url() . '/index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
// if (user_group_id() != 1 && !has_permission('access', 'read_customer_transaction')) {
//     redirect(root_url() . '/' . ADMINDIRNAME . '/dashboard.php');
// }

// Set Document Title
$document->setTitle("LISTAS DE PRECIOS");

// Add Script
$document->addScript('../assets/itsolution24/angular/controllers/PriceListController.js');

// ADD BODY CLASS
$document->setBodyClass('sidebar-collapse');

// Include Header and Footer
include("header.php");
include("left_sidebar.php");
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper" ng-controller="PriceListController">

    <!-- Content Start -->
    <section class="content">

        <?php if (DEMO) : ?>
            <div class="box">
                <div class="box-body">
                    <div class="alert alert-info mb-0">
                        <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">

            <!-- CustomerTransaction List Section Start-->
            <div class="col-xs-12">
                <div class="box box-success">
                    <div class="box-header">
                        <h3 class="box-title">
                                LISTA DE PRECIOS
                        </h3>
                    </div>
                    <div class='box-body'>
                        <div class="table-responsive">
                            <table id="#table-price-list" class="table table-bordered table-striped table-hovered" data-hide-colums="">
                                <thead>
                                    <tr class="bg-gray">
                                        <th class="w-5">
                                            <?= "ID" ?>
                                        </th>
                                        <th class="w-15">
                                            <?= "NOMBRE" ?>
                                        </th>
                                        <th class="w-10">
                                            <?= "USUARIO" ?>
                                        </th>
                                        <th class="w-20">
                                            <?= "CREADO" ?>
                                        </th>
                                        <th class="w-10">
                                            <?= "STATUS" ?>
                                        </th>
                                        <th class="w-10">
                                            <?= "VER" ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tfoot>
                                    <tr class="bg-gray">
                                        <th class="w-5">
                                        <?= "ID" ?>
                                        </th>
                                        <th class="w-15">
                                            <?= "NOMBRE" ?>
                                        </th>
                                        <th class="w-10">
                                            <?= "USUARIO" ?>
                                        </th>
                                        <th class="w-20">
                                            <?= "CREADO" ?>
                                        </th>
                                        <th class="w-10">
                                            <?= "STATUS" ?>
                                        </th>
                                        <th class="w-10">
                                            <?= "VER" ?>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Content End -->
</div>
<!-- Content Wrapper End -->

<?php include("footer.php"); ?>