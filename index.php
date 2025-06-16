import React, { useState, useEffect } from 'react';
import { MapPin, Heart, Baby, Plus, RefreshCw, User, Users } from 'lucide-react';

const MaternalChildHealthSystem = () => {
  const [map, setMap] = useState(null);
  const [markers, setMarkers] = useState([]);
  const [healthData, setHealthData] = useState([]);
  const [loading, setLoading] = useState(false);
  const [showAddForm, setShowAddForm] = useState(false);
  const [stats, setStats] = useState({
    total: 0,
    pregnant: 0,
    lowWeight: 0,
    normalWeight: 0
  });

  // Backend API URL (ใช้ Google Apps Script Web App)
  const API_URL = 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec';

  useEffect(() => {
    initializeMap();
    loadData();
  }, []);

  const initializeMap = () => {
    // Load Leaflet dynamically
    if (!window.L) {
      const leafletCSS = document.createElement('link');
      leafletCSS.rel = 'stylesheet';
      leafletCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css';
      document.head.appendChild(leafletCSS);

      const leafletJS = document.createElement('script');
      leafletJS.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
      leafletJS.onload = () => {
        const mapInstance = window.L.map('map').setView([13.7563, 100.5018], 6);
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors'
        }).addTo(mapInstance);
        setMap(mapInstance);
      };
      document.head.appendChild(leafletJS);
    }
  };

  const loadData = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${API_URL}?action=getData`);
      const data = await response.json();
      
      if (data.success) {
        setHealthData(data.data);
        updateMapMarkers(data.data);
        calculateStats(data.data);
      }
    } catch (error) {
      console.error('Error loading data:', error);
    } finally {
      setLoading(false);
    }
  };

  const updateMapMarkers = (data) => {
    if (!map) return;

    // Clear existing markers
    markers.forEach(marker => map.removeLayer(marker));
    const newMarkers = [];

    data.forEach(record => {
      const lat = parseFloat(record.latitude);
      const lng = parseFloat(record.longitude);
      
      if (isNaN(lat) || isNaN(lng)) return;

      const ga = parseFloat(record.GA) || 0;
      const weight = parseFloat(record.weight) || 0;
      
      let color, type, popupContent;

      if (ga > 0 && weight === 0) {
        // Pregnant woman
        color = '#3b82f6'; // Blue
        type = 'pregnant';
        popupContent = `
          <div class="p-3 min-w-48">
            <h3 class="text-lg font-bold text-blue-600 mb-2">🤰 หญิงตั้งครรภ์</h3>
            <p><strong>ชื่อ:</strong> ${record.mother_name || 'ไม่ระบุ'}</p>
            <p><strong>สามี:</strong> ${record.father_name || 'ไม่ระบุ'}</p>
            <p><strong>อายุครรภ์:</strong> ${ga} สัปดาห์</p>
            ${record.address ? `<p><strong>ที่อยู่:</strong> ${record.address}</p>` : ''}
          </div>
        `;
      } else if (weight > 0) {
        const isLowWeight = weight < 2500;
        color = isLowWeight ? '#f97316' : '#22c55e'; // Orange or Green
        type = isLowWeight ? 'lowWeight' : 'normalWeight';
        popupContent = `
          <div class="p-3 min-w-48">
            <h3 class="text-lg font-bold ${isLowWeight ? 'text-orange-600' : 'text-green-600'} mb-2">
              👶 ${record.child_name || 'ไม่ระบุชื่อ'}
            </h3>
            <p><strong>น้ำหนักแรกเกิด:</strong> ${weight.toLocaleString()} กรัม</p>
            <p><strong>ชื่อพ่อ:</strong> ${record.father_name || 'ไม่ระบุ'}</p>
            <p><strong>ชื่อแม่:</strong> ${record.mother_name || 'ไม่ระบุ'}</p>
            ${record.birth_date ? `<p><strong>วันเกิด:</strong> ${record.birth_date}</p>` : ''}
            ${record.address ? `<p><strong>ที่อยู่:</strong> ${record.address}</p>` : ''}
            <p class="font-bold ${isLowWeight ? 'text-orange-600' : 'text-green-600'}">
              ${isLowWeight ? '⚠️ น้ำหนักต่ำกว่าเกณฑ์' : '✅ น้ำหนักปกติ'}
            </p>
          </div>
        `;
      }

      const customIcon = window.L.divIcon({
        html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
        className: 'custom-marker',
        iconSize: [20, 20],
        iconAnchor: [10, 10]
      });

      const marker = window.L.marker([lat, lng], { icon: customIcon });
      marker.bindPopup(popupContent);
      marker.addTo(map);
      newMarkers.push(marker);
    });

    setMarkers(newMarkers);

    if (newMarkers.length > 0) {
      const group = new window.L.featureGroup(newMarkers);
      map.fitBounds(group.getBounds().pad(0.1));
    }
  };

  const calculateStats = (data) => {
    const total = data.length;
    const pregnant = data.filter(r => parseFloat(r.GA) > 0 && parseFloat(r.weight) === 0).length;
    const lowWeight = data.filter(r => parseFloat(r.weight) > 0 && parseFloat(r.weight) < 2500).length;
    const normalWeight = data.filter(r => parseFloat(r.weight) >= 2500).length;
    
    setStats({ total, pregnant, lowWeight, normalWeight });
  };

  const AddRecordForm = () => {
    const [formData, setFormData] = useState({
      type: 'pregnant',
      child_name: '',
      mother_name: '',
      father_name: '',
      weight: '',
      GA: '',
      latitude: '',
      longitude: '',
      address: '',
      birth_date: '',
      expected_date: ''
    });

    const handleSubmit = (e) => {
      e.preventDefault();
      setLoading(true);

      // Simulate API call
      setTimeout(async () => {
        try {
          const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'addRecord',
              data: formData
            })
          });

          const result = await response.json();
          
          if (result.success) {
            setShowAddForm(false);
            setFormData({
              type: 'pregnant',
              child_name: '',
              mother_name: '',
              father_name: '',
              weight: '',
              GA: '',
              latitude: '',
              longitude: '',
              address: '',
              birth_date: '',
              expected_date: ''
            });
            loadData(); // Reload data
          } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
          }
        } catch (error) {
          console.error('Error adding record:', error);
          alert('เกิดข้อผิดพลาดในการเพิ่มข้อมูล');
        } finally {
          setLoading(false);
        }
      }, 1000);
    };

    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <h2 className="text-xl font-bold mb-4">เพิ่มข้อมูลใหม่</h2>
          
          <div onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">ประเภทข้อมูล</label>
              <select
                value={formData.type}
                onChange={(e) => setFormData({...formData, type: e.target.value})}
                className="w-full p-2 border rounded-md"
              >
                <option value="pregnant">หญิงตั้งครรภ์</option>
                <option value="baby">ทารกแรกเกิด</option>
              </select>
            </div>

            {formData.type === 'baby' && (
              <div>
                <label className="block text-sm font-medium mb-1">ชื่อเด็ก</label>
                <input
                  type="text"
                  value={formData.child_name}
                  onChange={(e) => setFormData({...formData, child_name: e.target.value})}
                  className="w-full p-2 border rounded-md"
                  placeholder="ชื่อเด็ก"
                />
              </div>
            )}

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">ชื่อแม่</label>
                <input
                  type="text"
                  value={formData.mother_name}
                  onChange={(e) => setFormData({...formData, mother_name: e.target.value})}
                  className="w-full p-2 border rounded-md"
                  placeholder="ชื่อแม่"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">ชื่อพ่อ/สามี</label>
                <input
                  type="text"
                  value={formData.father_name}
                  onChange={(e) => setFormData({...formData, father_name: e.target.value})}
                  className="w-full p-2 border rounded-md"
                  placeholder="ชื่อพ่อ/สามี"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              {formData.type === 'baby' && (
                <div>
                  <label className="block text-sm font-medium mb-1">น้ำหนักแรกเกิด (กรัม)</label>
                  <input
                    type="number"
                    value={formData.weight}
                    onChange={(e) => setFormData({...formData, weight: e.target.value})}
                    className="w-full p-2 border rounded-md"
                    placeholder="เช่น 3000"
                  />
                </div>
              )}
              <div>
                <label className="block text-sm font-medium mb-1">อายุครรภ์ (สัปดาห์)</label>
                <input
                  type="number"
                  value={formData.GA}
                  onChange={(e) => setFormData({...formData, GA: e.target.value})}
                  className="w-full p-2 border rounded-md"
                  placeholder="เช่น 38"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">Latitude</label>
                <input
                  type="number"
                  step="any"
                  value={formData.latitude}
                  onChange={(e) => setFormData({...formData, latitude: e.target.value})}
                  className="w-full p-2 border rounded-md"
                  placeholder="เช่น 13.7563"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Longitude</label>
                <input
                  type="number"
                  step="any"
                  value={formData.longitude}
                  onChange={(e) => setFormData({...formData, longitude: e.target.value})}
                  className="w-full p-2 border rounded-md"
                  placeholder="เช่น 100.5018"
                  required
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">ที่อยู่</label>
              <textarea
                value={formData.address}
                onChange={(e) => setFormData({...formData, address: e.target.value})}
                className="w-full p-2 border rounded-md"
                placeholder="ที่อยู่"
                rows="2"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              {formData.type === 'baby' && (
                <div>
                  <label className="block text-sm font-medium mb-1">วันเกิด</label>
                  <input
                    type="date"
                    value={formData.birth_date}
                    onChange={(e) => setFormData({...formData, birth_date: e.target.value})}
                    className="w-full p-2 border rounded-md"
                  />
                </div>
              )}
              {formData.type === 'pregnant' && (
                <div>
                  <label className="block text-sm font-medium mb-1">กำหนดคลอด</label>
                  <input
                    type="date"
                    value={formData.expected_date}
                    onChange={(e) => setFormData({...formData, expected_date: e.target.value})}
                    className="w-full p-2 border rounded-md"
                  />
                </div>
              )}
            </div>

            <div className="flex justify-end space-x-2 pt-4">
              <button
                type="button"
                onClick={() => setShowAddForm(false)}
                className="px-4 py-2 text-gray-600 border rounded-md hover:bg-gray-50"
              >
                ยกเลิก
              </button>
              <button
                type="button"
                onClick={handleSubmit}
                disabled={loading}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                {loading ? 'กำลังบันทึก...' : 'บันทึก'}
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div className="container mx-auto px-4 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold flex items-center gap-2">
                <Heart className="text-pink-300" />
                ระบบติดตามการดูแลสุขภาพแม่และเด็ก
              </h1>
              <p className="text-blue-100 mt-1">โรงพยาบาลทุ่งหัวช้าง</p>
            </div>
            <div className="flex gap-2">
              <button
                onClick={loadData}
                disabled={loading}
                className="flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-md transition-colors"
              >
                <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
                รีเฟรช
              </button>
              <button
                onClick={() => setShowAddForm(true)}
                className="flex items-center gap-2 bg-green-500 hover:bg-green-600 px-4 py-2 rounded-md transition-colors"
              >
                <Plus className="w-4 h-4" />
                เพิ่มข้อมูล
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Stats */}
      <div className="container mx-auto px-4 py-6">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white p-4 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">ทั้งหมด</p>
                <p className="text-2xl font-bold text-gray-800">{stats.total}</p>
              </div>
              <Users className="w-8 h-8 text-gray-400" />
            </div>
          </div>
          <div className="bg-white p-4 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">หญิงตั้งครรภ์</p>
                <p className="text-2xl font-bold text-blue-600">{stats.pregnant}</p>
              </div>
              <User className="w-8 h-8 text-blue-400" />
            </div>
          </div>
          <div className="bg-white p-4 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">ทารกน้ำหนักต่ำ</p>
                <p className="text-2xl font-bold text-orange-600">{stats.lowWeight}</p>
              </div>
              <Baby className="w-8 h-8 text-orange-400" />
            </div>
          </div>
          <div className="bg-white p-4 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">ทารกน้ำหนักปกติ</p>
                <p className="text-2xl font-bold text-green-600">{stats.normalWeight}</p>
              </div>
              <Baby className="w-8 h-8 text-green-400" />
            </div>
          </div>
        </div>

        {/* Map */}
        <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
          <div className="p-4 border-b">
            <h2 className="text-lg font-semibold flex items-center gap-2">
              <MapPin className="w-5 h-5 text-blue-600" />
              แผนที่แสดงตำแหน่ง
            </h2>
          </div>
          <div className="relative">
            <div id="map" className="w-full h-96"></div>
            {/* Legend */}
            <div className="absolute top-4 right-4 bg-white/95 backdrop-blur-sm rounded-lg p-3 shadow-lg z-10">
              <h3 className="font-semibold text-sm mb-2">คำอธิบาย</h3>
              <div className="space-y-2 text-sm">
                <div className="flex items-center gap-2">
                  <div className="w-4 h-4 bg-blue-500 rounded-full border-2 border-white shadow"></div>
                  <span>หญิงตั้งครรภ์</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-4 h-4 bg-orange-500 rounded-full border-2 border-white shadow"></div>
                  <span>ทารกน้ำหนักต่ำ</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-4 h-4 bg-green-500 rounded-full border-2 border-white shadow"></div>
                  <span>ทารกน้ำหนักปกติ</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Footer */}
      <div className="bg-gray-800 text-white text-center py-4 mt-8">
        <p className="text-sm">สงวนลิขสิทธิ์ © 2568 โรงพยาบาลทุ่งหัวช้าง | Powered by Fz</p>
      </div>

      {/* Add Record Modal */}
      {showAddForm && <AddRecordForm />}

      {/* Loading Overlay */}
      {loading && (
        <div className="fixed inset-0 bg-black bg-opacity-25 flex items-center justify-center z-40">
          <div className="bg-white rounded-lg p-6 flex items-center gap-3">
            <RefreshCw className="w-5 h-5 animate-spin text-blue-600" />
            <span>กำลังโหลด...</span>
          </div>
        </div>
      )}
    </div>
  );
};

export default MaternalChildHealthSystem;