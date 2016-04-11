<?php namespace pineapple;

class Filters extends SystemModule
{

    public function route()
    {
        switch ($this->request->action) {
            case 'getClientData':
                $this->getClientData();
                break;

            case 'getSSIDData':
                $this->getSSIDData();
                break;

            case 'toggleClientMode':
                $this->toggleClientMode();
                break;

            case 'toggleSSIDMode':
                $this->toggleSSIDMode();
                break;
            case 'addClient':
                $this->addClient();
                break;

            case 'addSSID':
                $this->addSSID();
                break;

            case 'removeClient':
                $this->removeClient();
                break;

            case 'removeSSID':
                $this->removeSSID();
                break;
        }
    }

    private function getSSIDMode()
    {
        if (exec("hostapd_cli -i wlan0 karma_get_black_white") === "WHITE") {
            return "Allow";
        } else {
            return "Deny";
        }
    }

    private function getClientMode()
    {
        if (exec("hostapd_cli -i wlan0 karma_get_mac_black_white") === "WHITE") {
            return "Allow";
        } else {
            return "Deny";
        }
    }

    private function getSSIDFilters()
    {
        $ssidFilters = "";
        exec("pineapple karma list_ssids", $filters);
        foreach ($filters as $filter) {
            $ssidFilters .= "{$filter}\n";
        }
        return $ssidFilters;
    }

    private function getClientFilters()
    {
        $clientFilters = "";
        exec("pineapple karma list_macs", $filters);
        foreach ($filters as $filter) {
            $clientFilters .= "{$filter}\n";
        }
        return $clientFilters;
    }

    private function toggleClientMode()
    {
        if ($this->request->mode === "Allow") {
            exec("hostapd_cli -i wlan0 karma_mac_white");
        } else {
            exec("hostapd_cli -i wlan0 karma_mac_black");
        }
    }

    private function toggleSSIDMode()
    {
        if ($this->request->mode === "Allow") {
            exec("hostapd_cli -i wlan0 karma_white");
        } else {
            exec("hostapd_cli -i wlan0 karma_black");
        }
    }

    private function getClientData()
    {
        $mode = $this->getClientMode();
        $filters = $this->getClientFilters();
        $this->response = array("mode" => $mode, "clientFilters" => $filters);
    }

    private function getSSIDData()
    {
        $mode = $this->getSSIDMode();
        $filters = $this->getSSIDFilters();
        $this->response = array("mode" => $mode, "ssidFilters" => $filters);
    }

    private function addSSID()
    {
        if (isset($this->request->ssid)) {
            exec("pineapple karma add_ssid " . escapeshellarg($this->request->ssid));
            $this->getSSIDData();
        }
    }
    private function addClient()
    {
        if (isset($this->request->mac)) {
            exec("pineapple karma add_mac " . escapeshellarg($this->request->mac));
            $this->getClientData();
        }
    }

    private function removeSSID()
    {
        if (isset($this->request->ssid)) {
            exec("pineapple karma del_ssid " . escapeshellarg($this->request->ssid));
            $this->getSSIDData();
        }
    }
    private function removeClient()
    {
        if (isset($this->request->mac)) {
            exec("pineapple karma del_mac " . escapeshellarg($this->request->mac));
            $this->getClientData();
        }
    }
}
