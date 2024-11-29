jQuery(document).ready(function () {
    // Other code
    $('#add_company_tower').on('click', function () {
        $("#add_company_tower_name").modal('show');
    });

});

function toggleTowerSection() {
    $('#tower-name-input').toggle();
    $('#tower-name-drop-down').toggle();
}
