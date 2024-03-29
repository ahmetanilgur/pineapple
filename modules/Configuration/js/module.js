registerController("ConfigurationGeneralController", ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
	$scope.actionMessage = "";
	$scope.currentTimeZone = "";
	$scope.customOffset = "";
	$scope.oldPassword = "";
	$scope.newPassword = "";
	$scope.newPasswordRepeat = "";
	$scope.showPasswordSuccess = false;
	$scope.showPasswordError = false;

	$scope.timeZones = [
		{ value: 'GMT+12', description: "(GMT-12:00) Eniwetok, Kwajalein" },
		{ value: 'GMT+11', description: "(GMT-11:00) Midway Island, Samoa" },
		{ value: 'GMT+10', description: "(GMT-10) Hawaii" },
		{ value: 'GMT+9', description: "(GMT-9) Alaska" },
		{ value: 'GMT+8', description: "(GMT-8) Pacific Time (US & Canada)" },
		{ value: 'GMT+7', description: "(GMT-7) Mountain Time (US & Canada)" },
		{ value: 'GMT+6', description: "(GMT-6) Central Time (US & Canada), Mexico City" },
		{ value: 'GMT+5', description: "(GMT-5) Eastern Time (US & Canada), Bogota, Lima" },
		{ value: 'GMT+4', description: "(GMT-4) Atlantic Time (Canada), Caracas, La Paz" },
		{ value: 'GMT+3', description: "(GMT-3) Brazil, Buenos Aires, Georgetown" },
		{ value: 'GMT+2', description: "(GMT-2) MidAtlantic" },
		{ value: 'GMT+1', description: "(GMT-1) Azores, Cape Verde Islands" },
		{ value: 'UTC', description: "(UTC) Western Europe Time, London, Lisbon, Casablanca"},
		{ value: 'GMT-1', description: "(GMT+1) Brussels, Copenhagen, Madrid, Paris" },
		{ value: 'GMT-2', description: "(GMT+2) Kaliningrad, South Africa" },
		{ value: 'GMT-3', description: "(GMT+3) Baghdad, Riyadh, Moscow, St. Petersburg" },
		{ value: 'GMT-4', description: "(GMT+4) Abu Dhabi, Muscat, Baku, Tbilisi" },
		{ value: 'GMT-5', description: "(GMT+5) Ekaterinburg, Islamabad, Karachi, Tashkent" },
		{ value: 'GMT-6', description: "(GMT+6) -Almaty, Dhaka, Colombo" },
		{ value: 'GMT-7', description: "(GMT+7) Bangkok, Hanoi, Jakarta" },
		{ value: 'GMT-8', description: "(GMT+8) Beijing, Perth, Singapore, Hong Kong" },
		{ value: 'GMT-9', description: "(GMT+9) Tokyo, Seoul, Osaka, Sapporo, Yakutsk" },
		{ value: 'GMT-10', description: "(GMT+10) Eastern Australia, Guam, Vladivostok" },
		{ value: 'GMT-11', description: "(GMT+11) Magadan, Solomon Islands, New Caledonia" },
		{ value: 'GMT-12', description: "(GMT+12) Auckland, Wellington, Fiji, Kamchatka" }
	];


	$scope.getCurrentTimeZone = (function() {
		$api.request({
			module: "Configuration",
			action: "getCurrentTimeZone"
		}, function(response) {
			$scope.currentTimeZone = response.currentTimeZone;
		});
	});

	$scope.haltPineapple = (function() {
		if (confirm("Are you sure you want to shutdown your WiFi Pineapple?")) {
			$api.request({
				module: "Configuration",
				action: "haltPineapple"
			}, function(response) {
				if (response.success !== undefined) {
					$scope.actionMessage = "Your WiFi Pineapple is now shutting down. Once the LED has turned off, it is safe to unplug.";
					$timeout(function(){
					    $scope.actionMessage = "";
					}, 10000);
				}
			});			
		}
	});

	$scope.rebootPineapple = (function() {
		if (confirm("Are you sure you want to reboot your WiFi Pineapple?")) {
			$api.request({
				module: "Configuration",
				action: "rebootPineapple"
			}, function(response) {
				if (response.success !== undefined) {
					$scope.actionMessage = "Your WiFi Pineapple is now rebooting. You may need to reconnect once it is done.";
					$timeout(function(){
					    $scope.actionMessage = "";
					}, 10000);
				}
			});			
		}
	});

	$scope.resetPineapple = (function() {
		if (confirm("Are you sure you want to factory reset your WiFi Pineapple?\n\nThis will erase all data that has not been saved on the SD card.")) {
			$api.request({
				module: "Configuration",
				action: "resetPineapple"
			}, function(response) {
				if (response.success !== undefined) {
					$scope.actionMessage = "Your WiFi Pineapple is now restoring to factory defaults. This can take a few minutes and you will be disconnected.";
					$timeout(function(){
					    $scope.actionMessage = "";
					}, 10000);
				}
			});			
		}
	});

	$scope.changePassword = (function() {
		$api.request({
			module: 'Configuration',
			action: 'changePassword',
			oldPassword: $scope.oldPassword,
			newPassword: $scope.newPassword,
			newPasswordRepeat: $scope.newPasswordRepeat
		}, function(response) {
			if (response.success === true) {
				$scope.showPasswordSuccess = true;
				$timeout(function(){
				    $scope.showPasswordSuccess = false;
				}, 2000);
			} else {
				$scope.showPasswordError = true;
				$timeout(function(){
				    $scope.showPasswordError = false;
				}, 2000);
			}
			$scope.oldPassword = "";
			$scope.newPassword = "";
			$scope.newPasswordRepeat = "";
		});
	});

	$scope.changeTimezone = (function() {
		var tmpTimeZone = $scope.selectedTimeZone.value;
		if ($scope.customOffset.trim() !== "") {
			tmpTimeZone = $scope.customOffset;
		}
		$api.request({
			module: "Configuration",
			action: "changeTimeZone",
			timeZone: tmpTimeZone,

		}, function(response) {
			if (response.success !== undefined) {
				$scope.getCurrentTimeZone();
				$scope.customOffset = "";
			}
		});
	});

	$scope.getCurrentTimeZone();
}]);

registerController('ConfigurationLandingPageController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
	$scope.pageSaved = false;
	$scope.landingPage = '';
	$scope.landingPageStatus = 'Disabled';

	$api.request({
		module: 'Configuration',
		action: 'getLandingPageData'
	}, function(response) {
		$scope.landingPage = response.landingPage;
	});

	$scope.saveLandingPage = (function() {
		$api.request({
			module: 'Configuration',
			action: 'saveLandingPage',
			landingPageData: $scope.landingPage
		}, function(response) {
            if (response.success === true) {
                $scope.pageSaved = true;
                $timeout(function(){
                    $scope.pageSaved = false;
                }, 2000);
            }
		});
	});

	$scope.getLandingPageStatus = (function() {
		$api.request({
			module: 'Configuration',
			action: 'getLandingPageStatus'
		}, function(response) {
			if (response.error === undefined) {
				if (response.enabled === true) {
					$scope.landingPageStatus = 'Enabled';
				} else {
					$scope.landingPageStatus = 'Disabled';
				}
			}
		});
	});

	$scope.toggleLandingPage = (function() {
		var toggleAction = ($scope.landingPageStatus === 'Enabled') ? 'disableLandingPage' : 'enableLandingPage';
		$api.request({
			module: 'Configuration',
			action: toggleAction
		}, function(response) {
			if (response.error === undefined) {
				$scope.getLandingPageStatus();
			}
		});
	});

	$scope.getLandingPageStatus();
}]);