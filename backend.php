<?php
// config.php - การตั้งค่าระบบ
<?php
define('GOOGLE_SHEET_ID', '1JQ_vWiRMsZwZyNVim_v89u_o1S1isWYEGYie57d0PH0');
define('GOOGLE_APPS_SCRIPT_URL', 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec');

class HealthTrackingSystem {
    
    // ดึงข้อมูลจาก Google Sheets
    public function getSheetData() {
        $csvUrl = "https://docs.google.com/spreadsheets/d/" . GOOGLE_SHEET_ID . "/export?format=csv";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; HealthTracker/1.0)'
            ]
        ]);
        
        $csvData = file_get_contents($csvUrl, false, $context);
        
        if ($csvData === false) {
            throw new Exception('ไม่สามารถดึงข้อมูลจาก Google Sheets ได้');
        }
        
        return $this->parseCsvData($csvData);
    }
    
    // แปลงข้อมูล CSV เป็น Array
    private function parseCsvData($csvData) {
        $lines = explode("\n", $csvData);
        $headers = str_getcsv(array_shift($lines));
        $data = [];
        
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            
            $row = str_getcsv($line);
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
        
        return $data;
    }
    
    // เพิ่มข้อมูลใหม่
    public function addRecord($data) {
        $postData = [
            'action' => 'add',
            'data' => json_encode($data)
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($postData),
                'timeout' => 30
            ]
        ]);
        
        $result = file_get_contents(GOOGLE_APPS_SCRIPT_URL, false, $context);
        
        if ($result === false) {
            throw new Exception('ไม่สามารถเพิ่มข้อมูลได้');
        }
        
        return json_decode($result, true);
    }
    
    // จำแนกประเภทข้อมูล
    public function categorizeData($records) {
        $categories = [
            'pregnant' => [],
            'low_weight' => [],
            'normal_weight' => [],
            'unknown' => []
        ];
        
        foreach ($records as $record) {
            $ga = floatval($record['GA'] ?? 0);
            $weight = floatval($record['weight'] ?? 0);
            
            if ($ga > 0 && $weight == 0) {
                $categories['pregnant'][] = $record;
            } elseif ($weight > 0 && $weight < 2500) {
                $categories['low_weight'][] = $record;
            } elseif ($weight >= 2500) {
                $categories['normal_weight'][] = $record;
            } else {
                $categories['unknown'][] = $record;
            }
        }
        
        return $categories;
    }
}

// api.php - API Endpoints
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$healthSystem = new HealthTrackingSystem();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['action']) && $_GET['action'] === 'getData') {
                $data = $healthSystem->getSheetData();
                $categories = $healthSystem->categorizeData($data);
                
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'categories' => $categories,
                    'stats' => [
                        'total' => count($data),
                        'pregnant' => count($categories['pregnant']),
                        'low_weight' => count($categories['low_weight']),
                        'normal_weight' => count($categories['normal_weight'])
                    ]
                ]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (isset($input['action']) && $input['action'] === 'add') {
                $result = $healthSystem->addRecord($input['data']);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// index.php - หน้าแสดงผลหลัก
?>
