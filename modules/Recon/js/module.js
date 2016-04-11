registerController('ReconController', ['$api', '$scope', '$interval', function($api, $scope, $interval) {

	$scope.accessPoints = [];
	$scope.unassociatedClients = [];
	$scope.outOfRangeClients = [];

	$scope.scanDuration = '15';
	$scope.running = false;
	$scope.continuous = false;
	$scope.percent = 0;
	$scope.scanType = 'apOnly';

	$scope.loading = false;

	$scope.startScan = function() {
		if ($scope.running) {
			return;
		}
		sendScanRequest();
	};

	function checkScanStatus(newScan) {
		$scope.checkScanInterval = $interval(function() {
			$api.request({
				module: 'Recon',
				action: 'scanStatus',
				scan: newScan

			}, function(response) {
				if (response.completed === true) {
					$scope.percent = 100;
					parseScanResults(response);
					$scope.stopScan(true);
				} else {
					var percentage = Math.ceil(100 / ($scope.scanDuration / 5));
					if ($scope.percent + percentage > 100) {
						$scope.percent = 100;
					} else {
						$scope.percent += percentage;
					}
				}
			});
		}, 5000);
	}

	function sendScanRequest() {
		$scope.loading = true;
		$api.request({
			module: 'Recon',
			action: 'startScan',
			scanType: $scope.scanType,
			scanDuration: $scope.scanDuration
		}, function(response) {
			$scope.loading = false;
			$scope.running = true;
			checkScanStatus(response.scan);
		});
	}

	function parseScanResults(results) {
		$scope.accessPoints = [];
		$scope.unassociatedClients = [];
		$scope.outOfRangeClients = [];
		if (results.ap !== undefined) {
			var outOfRangeClients = [];
			var apArray = [];

			angular.forEach(results.ap, function(apData){
				var ap = {};
				ap['mac'] = apData.mac;
				ap['ssid'] = apData.ssid;
				ap['channel'] = apData.channel;
				ap['signal'] = Math.round((apData.quality.replace('/70', '')/70) * 100) + "%";
				ap['security'] = apData.security;
				apArray.push(ap);
			});

			if (results.clients !== undefined) {
				angular.forEach(results.clients, function(clients, ap){
					if (ap === "FF:FF:FF:FF:FF:FF") {
						$scope.unassociatedClients = clients;
					} else {
						var found = false;
						angular.forEach(apArray, function(apData, key){
							if (apData.mac == ap) {
								found = true;
								apData['clients'] = clients;
							}
						});
						if (!found) {
							var outOfRangeAP = {};
							outOfRangeAP['mac'] = ap;
							outOfRangeAP['clients'] = clients;
							outOfRangeClients.push(outOfRangeAP);
						}
					}
				});
			}
			$scope.accessPoints = apArray;
			$scope.outOfRangeClients = outOfRangeClients;
		}
	}

	$scope.stopScan = function(scripted) {
		$interval.cancel($scope.checkScanInterval);
		$scope.percent = 0;

		if (scripted === undefined) {
			$scope.running = false;
		} else if ($scope.continuous) {
			sendScanRequest();
		} else {
			$scope.running = false;
		}
	};

	$scope.$on('$destroy', function() {
	    $interval.cancel($scope.checkScanInterval);
	});

}]);