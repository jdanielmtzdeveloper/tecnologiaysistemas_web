window.angularApp.controller("PriceListController", [
    "$scope",
    "API_URL",
    "window",
    "jQuery",
    "$compile",
    "$uibModal",
    "$http",
    "$sce",
    function (
        $scope,
        API_URL,
        window,
        $,
        $compile,
        $uibModal,
        $http,
        $sce
    ) {
        "use strict";
        var dt = $("#table-price-list");
        
        //================
        // Start datatable
        //================

        dt.dataTable({
            "oLanguage": { sProcessing: "<img src='../assets/itsolution24/img/loading2.gif'>" },
            "processing": true,
            "dom": "lfBrtip",
            "serverSide": true,
            "ajax": API_URL + "/_inc/price_list.php",
            "order": [[0, "desc"]],
            "aLengthMenu": [
                [10, 25, 50, 100, 200, -1],
                [10, 25, 50, 100, 200, "All"]
            ],
            "aoColumns": [
                { data: "id_price_list" },
                { data: "name" },
                { data: "username" },
                { data: "created_at" },
                { data: "status" },
                { data: "btn_view" },
            ]
        });
        // console.log(":3");


        //================
        // End datatable
        //================
    }]);