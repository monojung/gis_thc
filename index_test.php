<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GIS ระบบติดตามแม่และเด็ก น้ำหนักต่ำกว่า 2500กรัม</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1em;
            color: #555;
            margin-bottom: 5px;
        }

        .stat-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .pregnant { color: #3498db; }
        .low-weight { color: #e67e22; }
        .normal-weight { color: #27ae60; }
        .total { color: #9b59b6; }

        .main-content {
            margin-bottom: 20px;
        }

        .map-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        #map {
            height: 700px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .legend {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .legend-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
            text-align: center;
        }

        .legend-items {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.9em;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .footer {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            color: #2c3e50;
            font-weight: bold;
        }

        .loading {
            text-align: center;
            padding: 40px;
            font-size: 1.2em;
            color: #666;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 GIS ระบบติดตามแม่และเด็ก น้ำหนักต่ำกว่า 2500กรัม</h1>
            <p>โรงพยาบาลทุ่งหัวช้าง - ระบบติดตามและเฝ้าระวังสุขภาพแม่และเด็ก</p>
        </div>
        <div id="loadingMessage" class="loading">
                <div style="font-size: 2em; margin-bottom: 10px;">⏳</div>
                กำลังโหลดข้อมูล...
            <p style="text-align: center; color: #000; font-size: 0.9em; margin-top: 20px;">
                ข้อมูลจาก Google Sheets - ระบบอาจใช้เวลาสักครู่ในการโหลดข้อมูล
            </p>
            </div>
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon total">📊</div>
                <div class="stat-number total" id="totalCount">0</div>
                <div class="stat-label">จำนวนรายการทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pregnant">🤰</div>
                <div class="stat-number pregnant" id="pregnantCount">0</div>
                <div class="stat-label">จำนวนหญิงตั้งครรภ์</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon low-weight">🍼</div>
                <div class="stat-number low-weight" id="lowWeightCount">0</div>
                <div class="stat-label">ทารกน้ำหนักต่ำ</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon normal-weight">✅</div>
                <div class="stat-number normal-weight" id="normalWeightCount">0</div>
                <div class="stat-label">ทารกน้ำหนักปกติ</div>
            </div>
        </div>

        <div class="main-content">
            <div class="map-container">
                <div id="map"></div>
            </div>
        </div>

        <div class="legend">
            <div class="legend-title">🗺️ คำอธิบายสัญลักษณ์บนแผนที่</div>
            <div class="legend-items">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #3498db;"></div>
                    <span>🤰 หญิงตั้งครรภ์</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #e67e22;"></div>
                    <span>🍼 ทารกน้ำหนักต่ำ (< 2500g)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #27ae60;"></div>
                    <span>✅ ทารกน้ำหนักปกติ (≥ 2500g)</span>
                </div>
            </div>
        </div>

        <div class="footer">
            GIS แม่และเด็ก โรงพยาบาลทุ่งหัวช้าง Powered by Fz
        </div>
    </div>

    <script>
        let map;
        let markers = [];
        let allData = [];

        // Initialize map
        function initMap() {
            // Center map on Thailand (approximate center)
            map = L.map('map').setView([15.8700, 100.9925], 6);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
        }

        // Load data from Google Sheets
        async function loadData() {
            const loadingMsg = document.getElementById('loadingMessage');
            if (!loadingMsg) {
               const loading = document.createElement('div');
                loading.id = 'loadingMessage';
                //loading.className = 'loading';
                //loading.innerHTML = '<div style="text-align: center;"><div style="font-size: 2em; margin-bottom: 10px;">⏳</div>กำลังโหลดข้อมูล...</div>';
                document.querySelector('.main-content').appendChild(loading);
            }

            try {
                const sheetId = '1JQ_vWiRMsZwZyNVim_v89u_o1S1isWYEGYie57d0PH0';
                
                // Try multiple URL formats for better compatibility
                const urlFormats = [
                    `https://docs.google.com/spreadsheets/d/${sheetId}/export?format=csv&gid=0`,
                    `https://docs.google.com/spreadsheets/d/${sheetId}/export?format=csv`,
                    `https://docs.google.com/spreadsheets/d/${sheetId}/gviz/tq?tqx=out:csv`
                ];

                let csvText = null;
                let response = null;

                // Try each URL format
                for (const url of urlFormats) {
                    try {
                        console.log(`Trying URL: ${url}`);
                        response = await fetch(url);
                        if (response.ok) {
                            csvText = await response.text();
                            console.log('Successfully loaded data from:', url);
                            break;
                        }
                    } catch (urlError) {
                        console.log(`Failed with URL ${url}:`, urlError.message);
                        continue;
                    }
                }

                if (!csvText) {
                    throw new Error('ไม่สามารถเข้าถึงข้อมูลได้ กรุณาตรวจสอบว่า Google Sheet ได้รับการแชร์แบบสาธารณะแล้ว');
                }

                console.log('Raw CSV data preview:', csvText.substring(0, 500));
                
                Papa.parse(csvText, {
                    header: true,
                    skipEmptyLines: true,
                    dynamicTyping: true,
                    complete: function(results) {
                        if (loadingMsg) loadingMsg.remove();
                        
                        console.log('Parsed data:', results.data);
                        console.log('Headers detected:', results.meta.fields);
                        
                        allData = results.data.filter(row => {
                            // Filter out completely empty rows
                            return Object.values(row).some(value => 
                                value !== null && value !== undefined && value !== ''
                            );
                        });
                        
                        console.log('Filtered data count:', allData.length);
                        
                        if (allData.length === 0) {
                            showError('ไม่พบข้อมูลในไฟล์ หรือรูปแบบข้อมูลไม่ถูกต้อง');
                            return;
                        }
                        
                        processData();
                    },
                    error: function(error) {
                        if (loadingMsg) loadingMsg.remove();
                        console.error('Error parsing CSV:', error);
                        showError('เกิดข้อผิดพลาดในการแปลงข้อมูล CSV: ' + error.message);
                    }
                });
            } catch (error) {
                if (loadingMsg) loadingMsg.remove();
                console.error('Error loading data:', error);
                showError(error.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล');
            }
        }

        // Process and display data
        function processData() {
            if (!allData || allData.length === 0) {
                showError('ไม่พบข้อมูลในระบบ');
                return;
            }

            clearMarkers();
            
            let pregnantCount = 0;
            let lowWeightCount = 0;
            let normalWeightCount = 0;

            // Get all possible column names for flexibility
            const sampleRow = allData[0];
            const columns = Object.keys(sampleRow);
            console.log('Available columns:', columns);

            // Find coordinate columns with multiple possible names
            const latColumns = columns.filter(col => 
                /lat|ละติจูด|latitude/i.test(col)
            );
            const lngColumns = columns.filter(col => 
                /lng|lon|ลองจิจูด|longitude/i.test(col)
            );

            console.log('Latitude columns found:', latColumns);
            console.log('Longitude columns found:', lngColumns);

            allData.forEach((row, index) => {
                if (!row || Object.keys(row).length === 0) return;

                // Get coordinates using flexible column matching
                let lat, lng;
                
                // Try to find valid coordinates
                for (const latCol of latColumns) {
                    const latVal = parseFloat(row[latCol]);
                    if (!isNaN(latVal) && latVal >= -90 && latVal <= 90) {
                        lat = latVal;
                        break;
                    }
                }
                
                for (const lngCol of lngColumns) {
                    const lngVal = parseFloat(row[lngCol]);
                    if (!isNaN(lngVal) && lngVal >= -180 && lngVal <= 180) {
                        lng = lngVal;
                        break;
                    }
                }

                // If no valid coordinates found, skip this row
                if (isNaN(lat) || isNaN(lng)) {
                    console.warn(`Invalid coordinates for row ${index}:`, {
                        row: row,
                        lat: lat,
                        lng: lng
                    });
                    return;
                }

                // Helper function to get value from multiple possible column names
                const getValue = (possibleNames) => {
                    for (const name of possibleNames) {
                        const col = columns.find(c => new RegExp(name, 'i').test(c));
                        if (col && row[col]) return row[col];
                    }
                    return 'ไม่ระบุ';
                };

                // Helper function to get numeric value
                const getNumericValue = (possibleNames) => {
                    for (const name of possibleNames) {
                        const col = columns.find(c => new RegExp(name, 'i').test(c));
                        if (col && row[col]) {
                            const val = parseFloat(row[col]);
                            if (!isNaN(val)) return val;
                        }
                    }
                    return 0;
                };

                // Get common fields
                const name = getValue(['ชื่อ', 'name', 'ชื่อ-สกุล']);
                const babyname = getValue(['babyname', 'ชื่อเด็ก']);
                const address = getValue(['ที่อยู่', 'address', 'addr']);
                const type = getValue(['ประเภท', 'type', 'สถานะ', 'status']);
                const weight = getNumericValue(['น้ำหนัก', 'weight', 'wt']);

                let markerType, color, icon, popupContent;

                // Determine marker type based on available data
                if (type.includes('ตั้งครรภ์') || type.includes('pregnant') || 
                    getValue(['อายุครรภ์', 'pregnancy', 'gestational']) !== 'ไม่ระบุ') {
                    
                    // This is pregnancy data
                    markerType = 'pregnant';
                    color = '#3498db';
                    icon = '🤰';
                    pregnantCount++;
                    
                    const husband = getValue(['สามี', 'husband', 'ชื่อสามี']);
                    const gestationalAge = getValue(['อายุครรภ์', 'pregnancy', 'gestational', 'weeks']);
                    
                    popupContent = `
                        <div style="min-width: 200px;">
                            <h3 style="color: #3498db; margin-bottom: 10px;">🤰 หญิงตั้งครรภ์</h3>
                            <p><strong>ชื่อ:</strong> ${name}</p>
                            <p><strong>สามี:</strong> ${husband}</p>
                            <p><strong>อายุครรภ์:</strong> ${gestationalAge}</p>
                            <p><strong>ที่อยู่:</strong> ${address}</p>
                        </div>
                    `;
                } else {
                    // This is newborn data
                    if (weight > 0 && weight < 2500) {
                        markerType = 'low-weight';
                        color = '#e67e22';
                        icon = '🍼';
                        lowWeightCount++;
                    } else if (weight >= 2500) {
                        markerType = 'normal-weight';
                        color = '#27ae60';
                        icon = '✅';
                        normalWeightCount++;
                    } else {
                        // If no weight specified, try to determine from type or default to normal
                        if (type.includes('ต่ำ') || type.includes('low')) {
                            markerType = 'low-weight';
                            color = '#e67e22';
                            icon = '🍼';
                            lowWeightCount++;
                        } else {
                            markerType = 'normal-weight';
                            color = '#27ae60';
                            icon = '✅';
                            normalWeightCount++;
                        }
                    }
                    
                    const father = getValue(['ชื่อพ่อ', 'father', 'พ่อ', 'dad']);
                    const mother = getValue(['ชื่อแม่', 'mother', 'แม่', 'mom']);
                    const status = weight > 0 && weight < 2500 ? 'น้ำหนักต่ำกว่าเกณฑ์' : 'น้ำหนักปกติ';
                    
                    popupContent = `
                        <div style="min-width: 200px;">
                            <h3 style="color: ${color}; margin-bottom: 10px;">${icon} ทารกแรกเกิด</h3>
                            <p><strong>ชื่อเด็ก:</strong> ${babyname}</p>
                            <p><strong>น้ำหนัก:</strong> ${weight > 0 ? weight + ' กรัม' : 'ไม่ระบุ'}</p>
                            <p><strong>สถานะ:</strong> ${status}</p>
                            <p><strong>ชื่อพ่อ:</strong> ${father}</p>
                            <p><strong>ชื่อแม่:</strong> ${mother}</p>
                            <p><strong>ที่อยู่:</strong> ${address}</p>
                        </div>
                    `;
                }

                // Create marker
                const marker = L.circleMarker([lat, lng], {
                    color: 'white',
                    fillColor: color,
                    fillOpacity: 0.8,
                    radius: 10,
                    weight: 2
                }).addTo(map);

                marker.bindPopup(popupContent);
                markers.push(marker);
            });

            // Update statistics
            const totalCount = pregnantCount + lowWeightCount + normalWeightCount;
            document.getElementById('totalCount').textContent = totalCount;
            document.getElementById('pregnantCount').textContent = pregnantCount;
            document.getElementById('lowWeightCount').textContent = lowWeightCount;
            document.getElementById('normalWeightCount').textContent = normalWeightCount;

            console.log('Statistics:', {
                total: totalCount,
                pregnant: pregnantCount,
                lowWeight: lowWeightCount,
                normal: normalWeightCount
            });

            // Fit map to markers if any exist
            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            } else {
                showError('ไม่พบข้อมูลที่มีพิกัดที่ถูกต้อง กรุณาตรวจสอบข้อมูล Latitude และ Longitude');
            }
        }

        // Clear all markers
        function clearMarkers() {
            markers.forEach(marker => {
                map.removeLayer(marker);
            });
            markers = [];
        }

        // Show error message
        function showError(message) {
            // Remove existing error messages
            const existingErrors = document.querySelectorAll('.error');
            existingErrors.forEach(error => error.remove());

            const container = document.querySelector('.container');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error';
            errorDiv.innerHTML = `
                <h3>⚠️ เกิดข้อผิดพลาด</h3>
                <p>${message}</p>
                <div style="margin-top: 15px;">
                    <button onclick="location.reload()" style="margin-right: 10px; padding: 8px 16px; background: #c62828; color: white; border: none; border-radius: 4px; cursor: pointer;">รีโหลดหน้า</button>
                    <button onclick="loadData()" style="padding: 8px 16px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer;">ลองใหม่</button>
                </div>
                <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
                    <strong>คำแนะนำ:</strong><br>
                    • ตรวจสอบว่า Google Sheet ได้รับการแชร์เป็น "ดูได้สำหรับทุกคนที่มีลิงก์"<br>
                    • ตรวจสอบว่ามีคอลัมน์ latitude, longitude (หรือ ละติจูด, ลองจิจูด)<br>
                    • ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต
                </div>
            `;
            
            // Insert after header
            const header = document.querySelector('.header');
            header.insertAdjacentElement('afterend', errorDiv);
        }

        // Initialize the application
        function init() {
            initMap();
            loadData();
            
            // Auto-refresh data every 5 minutes
            setInterval(loadData, 30000);
        }

        // Start the application when page loads
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>