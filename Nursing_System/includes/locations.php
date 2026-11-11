<?php
/**
 * Philippines Location Data
 * Province -> Municipality -> Barangay hierarchy
 */

function getPhilippinesLocations() {
    return [
        'Bulacan' => [
            'Meycauayan' => [
                'Barile', 'Borol', 'Calaanan', 'Coloong', 'Galing', 
                'Langka', 'Laya', 'Lipat', 'Listerio', 'Malinta',
                'Niog', 'Pangarap', 'Pinagbuhatan', 'Poblacion', 'Sikat'
            ],
            'Valenzuela' => [
                'Arkong-Bato', 'Bagbaguin', 'Bisig', 'Canumay', 'Coloong',
                'Daan-Lungsod', 'Galing', 'Hooker', 'Kalayaan', 'Karuhatan',
                'Lampak', 'Langka', 'Mabolo', 'Malanday', 'Malinta'
            ],
            'Bocaue' => [
                'Bahay', 'Bambang', 'Binunga', 'Coloong', 'Cupang',
                'Galing', 'Langka', 'Laya', 'Lipat', 'Mahabang-Dahilan',
                'Mahimay', 'Maligaya', 'Mataas', 'Melawin', 'Minuyan'
            ],
            'Marilao' => [
                'Abangan', 'Burol', 'Catanghalan', 'Chalan', 'Cuyab',
                'Naglingao', 'Pangarap', 'Patubig', 'Piyong', 'Tabing-Karagatan'
            ],
            'Obando' => [
                'Biglang-Laya', 'Bigte', 'Kalikasan', 'Kinabuchi', 'Loma',
                'Pag-Asa', 'Panginabangan', 'Pariahan', 'Parulan', 'Peñaranda'
            ]
        ],
        'Metro Manila' => [
            'Manila' => [
                'Balintawak', 'Bangolo', 'Binondo', 'Gagalangin', 'Intramuros',
                'Ipil', 'Julo', 'Kaligayahan', 'Kinabukasan', 'Laon-Laan',
                'Lavezares', 'Letre', 'Libis', 'Longos', 'Luneta',
                'Magsaysay', 'Maisahan', 'Malate', 'Malibay', 'Malinaw'
            ],
            'Quezon City' => [
                'Agora', 'Almanza', 'Apelo Cruz', 'Bagay-Bagay', 'Bagbag',
                'Bagumbayan', 'Balikatan', 'Balintawak', 'Balite', 'Balubad',
                'Balyani', 'Bambang', 'Banaba', 'Banawa', 'Banawe'
            ],
            'Makati' => [
                'Bangkal', 'Barrio Magsaysay', 'Barrio Olimpo', 'Bel-Air', 'Benedikto Reyes',
                'Binakayan', 'Bingwisan', 'Burangis', 'Carmona', 'Cembo',
                'Comembo', 'Dasmariñas', 'Dato', 'De La Costa', 'Dela Paz'
            ],
            'Pasay City' => [
                'Baclaran', 'Bangad', 'Barnachea', 'Bgy. 1', 'Bgy. 2',
                'Bgy. 3', 'Bgy. 4', 'Bgy. 5', 'Bgy. 6', 'Bgy. 7',
                'Bgy. 8', 'Bgy. 9', 'Bgy. 10', 'Bgy. 11', 'Bgy. 12'
            ]
        ],
        'Cavite' => [
            'Kawit' => [
                'Aguinaldo', 'Balangkas', 'Balsahan', 'Banay', 'Bandera',
                'Bangkal', 'Banilad', 'Bañong-Parang', 'Banquilao', 'Banrisya'
            ],
            'Imus' => [
                'Alapan', 'Bucal', 'Butsukan', 'Habagat', 'Kanluran',
                'Kaybiga', 'Panapaan', 'Anunas', 'Tagupo', 'Talaba'
            ],
            'Bacoor' => [
                'Aguho', 'Alapan I-A', 'Alapan I-B', 'Alapan I-C', 'Bancal',
                'Banusil', 'Banyong', 'Capul', 'Dao', 'Dasmariñas'
            ]
        ],
        'Laguna' => [
            'Santa Rosa' => [
                'Aplaya', 'Balabago', 'Banay', 'Banlic', 'Baliti',
                'Biñan', 'Butas', 'Cabuyao', 'Casuarina', 'Catindig'
            ],
            'San Pedro' => [
                'Bagong-Tahanan', 'Bagumbayan', 'Banaba', 'Barangka', 'Bayuyungan',
                'Bubuyan', 'Burol', 'Caloocan', 'Canumay', 'Casimiro'
            ],
            'Biñan' => [
                'Ampid', 'Asilo', 'Bagay-Bagay', 'Bagumbayan', 'Baintong',
                'Balanoy', 'Balantakay', 'Balao', 'Balas', 'Balasubas'
            ]
        ],
        'Rizal' => [
            'Antipolo' => [
                'Bagong-Silang', 'Bagumbayan', 'Bailang', 'Batingan', 'Bicutan',
                'Blumentritt', 'Bocobo', 'Boni', 'Bunot', 'Caruncho',
                'Casile', 'Cavil', 'Cayuman', 'Concepcion', 'Dalig'
            ],
            'Taytay' => [
                'Bagong-Nayon', 'Bagumbayan', 'Bagumbayan-Itaas', 'Batuhan', 'Binangonan',
                'Binwangan', 'Biyaya', 'Bukal', 'Bulacan', 'Calangyuan'
            ],
            'Marikina' => [
                'Barangka', 'Barangka-Ilaya', 'Barangka-Ibaba', 'Baruti', 'Concepcion',
                'Tumana', 'Marikina Heights', 'San Roque', 'Tipas', 'Nangka'
            ]
        ]
    ];
}

function insertLocationData($conn) {
    $locations = getPhilippinesLocations();
    
    try {
        // Insert provinces
        $provinceMap = [];
        foreach ($locations as $provinceName => $municipalities) {
            $stmt = $conn->prepare("INSERT INTO provinces (province_name) VALUES (?)");
            $stmt->bind_param("s", $provinceName);
            $stmt->execute();
            $provinceMap[$provinceName] = $conn->insert_id;
        }
        
        // Insert municipalities and barangays
        foreach ($locations as $provinceName => $municipalities) {
            $provinceId = $provinceMap[$provinceName];
            
            foreach ($municipalities as $municipalityName => $barangays) {
                $stmt = $conn->prepare("INSERT INTO municipalities (province_id, municipality_name) VALUES (?, ?)");
                $stmt->bind_param("is", $provinceId, $municipalityName);
                $stmt->execute();
                $municipalityId = $conn->insert_id;
                
                // Insert barangays
                foreach ($barangays as $barangayName) {
                    $stmt = $conn->prepare("INSERT INTO barangays (municipality_id, barangay_name) VALUES (?, ?)");
                    $stmt->bind_param("is", $municipalityId, $barangayName);
                    $stmt->execute();
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        return "Error inserting location data: " . $e->getMessage();
    }
}

function getProvinces($conn) {
    $result = $conn->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getMunicipaities($conn, $provinceId) {
    $stmt = $conn->prepare("SELECT municipality_id, municipality_name FROM municipalities WHERE province_id = ? ORDER BY municipality_name");
    $stmt->bind_param("i", $provinceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getBarangays($conn, $municipalityId) {
    $stmt = $conn->prepare("SELECT barangay_id, barangay_name FROM barangays WHERE municipality_id = ? ORDER BY barangay_name");
    $stmt->bind_param("i", $municipalityId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
