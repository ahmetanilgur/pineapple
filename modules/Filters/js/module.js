registerController('clientFilterController', ['$api', '$scope', function($api, $scope) {
    $scope.mode = '';
    $scope.mac = '';
    $scope.clientFilters = '';

    $scope.toggleMode = (function() {
        if ($scope.mode == 'Allow') {
            $scope.mode = 'Deny';
        } else {
            $scope.mode = 'Allow';
        }
        $api.request({
            module: 'Filters',
            action: 'toggleClientMode',
            mode: $scope.mode
        });
    });

    $scope.addClient = (function() {
        $api.request({
            module: 'Filters',
            action: 'addClient',
            mac: $scope.mac
        }, function(response) {
            if (response.error === undefined) {
                $scope.clientFilters = response.clientFilters;
                $scope.mac = "";
            }
        });
    });

    $scope.removeClient = (function() {
        $api.request({
            module: 'Filters',
            action: 'removeClient',
            mac: $scope.mac
        }, function(response) {
            if (response.error === undefined) {
                $scope.clientFilters = response.clientFilters;
                $scope.mac = "";
            }
        });
    });

    $api.request({
        module: 'Filters',
        action: 'getClientData'
    }, function(response) {
        if (response.error === undefined) {
            $scope.mode = response.mode;
            $scope.clientFilters = response.clientFilters;
        }
    });
}]);

registerController('ssidFilterController', ['$api', '$scope', function($api, $scope) {
    $scope.mode = '';
    $scope.ssid = '';
    $scope.ssidFilters = '';

    $scope.toggleMode = (function() {
        if ($scope.mode == 'Allow') {
            $scope.mode = 'Deny';
        } else {
            $scope.mode = 'Allow';
        }
        $api.request({
            module: 'Filters',
            action: 'toggleSSIDMode',
            mode: $scope.mode
        });
    });

    $scope.addSSID = (function() {
        $api.request({
            module: 'Filters',
            action: 'addSSID',
            ssid: $scope.ssid
        }, function(response) {
            if (response.error === undefined) {
                $scope.ssidFilters = response.ssidFilters;
                $scope.ssid = "";
            }
        });
    });

    $scope.removeSSID = (function() {
        $api.request({
            module: 'Filters',
            action: 'removeSSID',
            ssid: $scope.ssid
        }, function(response) {
            if (response.error === undefined) {
                $scope.ssidFilters = response.ssidFilters;
                $scope.ssid = "";
            }
        });
    });

    $api.request({
        module: 'Filters',
        action: 'getSSIDData'
    }, function(response) {
        if (response.error === undefined) {
            $scope.mode = response.mode;
            $scope.ssidFilters = response.ssidFilters;
        }
    });
}]);

function getClientLineNumber(textarea) {
    var lineNumber = textarea.value.substr(0, textarea.selectionStart).split("\n").length;
    var mac = textarea.value.split("\n")[lineNumber-1].trim();
    $("input[name='mac']").val(mac).trigger('input');
}

function getSSIDLineNumber(textarea) {
    var lineNumber = textarea.value.substr(0, textarea.selectionStart).split("\n").length;
    var ssid = textarea.value.split("\n")[lineNumber-1].trim();
    $("input[name='ssid']").val(ssid).trigger('input');
}