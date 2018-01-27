$.fn.dataTable.render.actionButtons = function () {
    return function ( data, type, row ) {
        return '<a href="/' + data[0].route + '/' + data[0].action + '/' + data[0].id + '" class="btn btn-primary btn-sm"><i class="fa ' + data[0].icon + '"></i></a>';
    };
};
