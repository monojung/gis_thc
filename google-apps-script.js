// Google Apps Script Code.gs
// สำหรับใช้เป็น Web App Backend

// Configuration
const SPREADSHEET_ID = '1JQ_vWiRMsZwZyNVim_v89u_o1S1isWYEGYie57d0PH0';
const SHEET_NAME = 'Sheet1';

// Headers for the spreadsheet
const HEADERS = [
  'id', 'child_name', 'mother_name', 'father_name', 
  'weight', 'GA', 'latitude', 'longitude', 
  'address', 'birth_date', 'expected_date', 'created_at', 'updated_at'
];

/**
 * Main function to handle HTTP requests
 */
function doGet(e) {
  try {
    const action = e.parameter.action || 'getData';
    
    switch (action) {
      case 'getData':
        return handleGetData();
      case 'getStats':
        return handleGetStats();
      default:
        return createResponse(false, 'Invalid action for GET request');
    }
  } catch (error) {
    console.error('Error in doGet:', error);
    return createResponse(false, 'Internal server error: ' + error.toString());
  }
}

/**
 * Handle POST requests
 */
function doPost(e) {
  try {
    const requestData = JSON.parse(e.postData.contents);
    const action = requestData.action;
    
    switch (action) {
      case 'addRecord':
        return handleAddRecord(requestData.data);
      case 'updateRecord':
        return handleUpdateRecord(requestData.id, requestData.data);
      case 'deleteRecord':
        return handleDeleteRecord(requestData.id);
      default:
        return createResponse(false, 'Invalid action for POST request');
    }
  } catch (error) {
    console.error('Error in doPost:', error);
    return createResponse(false, 'Internal server error: ' + error.toString());
  }
}

/**
 * Get all data from spreadsheet
 */
function handleGetData() {
  try {
    const sheet = getOrCreateSheet();
    const range = sheet.getDataRange();
    
    if (range.getNumRows() === 0) {
      return createResponse(true, 'No data found', []);
    }
    
    const values = range.getValues();
    const headers = values[0];
    const dataRows = values.slice(1);
    
    // Convert to objects
    const data = dataRows.map(row => {
      const obj = {};
      headers.forEach((header, index) => {
        obj[header] = row[index] || '';
      });
      return obj;
    });
    
    return createResponse(true, 'Data retrieved successfully', data);
  } catch (error) {
    console.error('Error getting data:', error);
    return createResponse(false, 'Failed to retrieve data: ' + error.toString());
  }
}

/**
 * Add new record to spreadsheet
 */
function handleAddRecord(data) {
  try {
    if (!data.latitude || !data.longitude) {
      return createResponse(false, 'Latitude and longitude are required');
    }
    
    const sheet = getOrCreateSheet();
    
    // Generate ID and timestamps
    const id = Utilities.getUuid();
    const now = new Date().toISOString();
    
    // Create row data based on headers
    const rowData = [];
    HEADERS.forEach(header => {
      switch (header) {
        case 'id':
          rowData.push(id);
          break;
        case 'created_at':
        case 'updated_at':
          rowData.push(now);
          break;
        default:
          rowData.push(data[header] || '');
      }
    });
    
    // Add row to sheet
    sheet.appendRow(rowData);
    
    return createResponse(true, 'Record added successfully', { id: id });
  } catch (error) {
    console.error('Error adding record:', error);
    return createResponse(false, 'Failed to add record: ' + error.toString());
  }
}

/**
 * Update existing record
 */
function handleUpdateRecord(recordId, data) {
  try {
    const sheet = getOrCreateSheet();
    const range = sheet.getDataRange();
    const values = range.getValues();
    
    if (values.length === 0) {
      return createResponse(false, 'No data found');
    }
    
    const headers = values[0];
    const idColumnIndex = headers.indexOf('id');
    
    if (idColumnIndex === -1) {
      return createResponse(false, 'ID column not found');
    }
    
    // Find row to update
    let rowIndex = -1;
    for (let i = 1; i < values.length; i++) {
      if (values[i][idColumnIndex] === recordId) {
        rowIndex = i + 1; // Sheet rows are 1-indexed
        break;
      }
    }
    
    if (rowIndex === -1) {
      return createResponse(false, 'Record not found');
    }
    
    // Update row data
    const updatedRow = [];
    headers.forEach(header => {
      switch (header) {
        case 'id':
          updatedRow.push(recordId);
          break;
        case 'updated_at':
          updatedRow.push(new Date().toISOString());
          break;
        case 'created_at':
          // Keep original created_at
          updatedRow.push(values[rowIndex - 1][headers.indexOf('created_at')] || '');
          break;
        default:
          updatedRow.push(data[header] || '');
      }
    });
    
    // Update the row
    const updateRange = sheet.getRange(rowIndex, 1, 1, headers.length);
    updateRange.setValues([updatedRow]);
    
    return createResponse(true, 'Record updated successfully');
  } catch (error) {
    console.error('Error updating record:', error);
    return createResponse(false, 'Failed to update record: ' + error.toString());
  }
}

/**
 * Delete record
 */
function handleDeleteRecord(recordId) {
  try {
    const sheet = getOrCreateSheet();
    const range = sheet.getDataRange();
    const values = range.getValues();
    
    if (values.length === 0) {
      return createResponse(false, 'No data found');
    }
    
    const headers = values[0];
    const idColumnIndex = headers.indexOf('id');
    
    if (idColumnIndex === -1) {
      return createResponse(false, 'ID column not found');
    }
    
    // Find row to delete
    let rowIndex = -1;
    for (let i = 1; i < values.length; i++) {
      if (values[i][idColumnIndex] === recordId) {
        rowIndex = i + 1; // Sheet rows are 1-indexed
        break;
      }
    }
    
    if (rowIndex === -1) {
      return createResponse(false, 'Record not found');
    }
    
    // Delete the row
    sheet.deleteRow(rowIndex);
    
    return createResponse(true, 'Record deleted successfully');
  } catch (error) {
    console.error('Error deleting record:', error);
    return createResponse(false, 'Failed to delete record: ' + error.toString());
  }
}

/**
 * Get statistics
 */
function handleGetStats() {
  try {
    const sheet = getOrCreateSheet();
    const range = sheet.getDataRange();
    
    if (range.getNumRows() === 0) {
      return createResponse(true, 'No data found', {
        total: 0,
        pregnant: 0,
        lowWeight: 0,
        normalWeight: 0
      });
    }
    
    const values = range.getValues();
    const headers = values[0];
    const dataRows = values.slice(1);
    
    // Convert to objects for easier processing
    const data = dataRows.map(row => {
      const obj = {};
      headers.forEach((header, index) => {
        obj[header] = row[index] || '';
      });
      return obj;
    });
    
    // Calculate statistics
    const total = data.length;
    const pregnant = data.filter(record => {
      const ga = parseFloat(record.GA) || 0;
      const weight = parseFloat(record.weight) || 0;
      return ga > 0 && weight === 0;
    }).length;
    
    const lowWeight = data.filter(record => {
      const weight = parseFloat(record.weight) || 0;
      return weight > 0 && weight < 2500;
    }).length;
    
    const normalWeight = data.filter(record => {
      const weight = parseFloat(record.weight) || 0;
      return weight >= 2500;
    }).length;
    
    const stats = {
      total,
      pregnant,
      lowWeight,
      normalWeight
    };
    
    return createResponse(true, 'Statistics retrieved successfully', stats);
  } catch (error) {
    console.error('Error getting stats:', error);
    return createResponse(false, 'Failed to retrieve statistics: ' + error.toString());
  }
}

/**
 * Get or create sheet with proper headers
 */
function getOrCreateSheet() {
  try {
    const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
    let sheet;
    
    try {
      sheet = spreadsheet.getSheetByName(SHEET_NAME);
    } catch (e) {
      // Sheet doesn't exist, create it
      sheet = spreadsheet.insertSheet(SHEET_NAME);
    }
    
    // Check if headers exist
    const range = sheet.getDataRange();
    if (range.getNumRows() === 0) {
      // Add headers
      sheet.getRange(1, 1, 1, HEADERS.length).setValues([HEADERS]);
      
      // Format header row
      const headerRange = sheet.getRange(1, 1, 1, HEADERS.length);
      headerRange.setFontWeight('bold');
      headerRange.setBackground('#4285f4');
      headerRange.setFontColor('white');
    }
    
    return sheet;
  } catch (error) {
    console.error('Error accessing sheet:', error);
    throw new Error('Cannot access spreadsheet: ' + error.toString());
  }
}

/**
 * Create standardized response
 */
function createResponse(success, message, data = null) {
  const response = {
    success: success,
    message: message,
    timestamp: new Date().toISOString()
  };
  
  if (data !== null) {
    response.data = data;
  }
  
  return ContentService
    .createTextOutput(JSON.stringify(response))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * Test function - can be used to test the script
 */
function testScript() {
  console.log('Testing Google Apps Script...');
  
  // Test getting data
  const result = handleGetData();
  console.log('Get data result:', result.getContent());
  
  // Test adding sample data
  const sampleData = {
    mother_name: 'นางสาวทดสอบ',
    father_name: 'นายทดสอบ',
    GA: '38',
    latitude: '13.7563',
    longitude: '100.5018',
    address: 'ที่อยู่ทดสอบ'
  };
  
  const addResult = handleAddRecord(sampleData);
  console.log('Add record result:', addResult.getContent());
}

/**
 * Initialize spreadsheet with sample data (optional)
 */
function initializeWithSampleData() {
  const sampleData = [
    {
      mother_name: 'นางสมศรี ใจดี',
      father_name: 'นายสมชาย ใจดี', 
      GA: '36',
      latitude: '13.7563',
      longitude: '100.5018',
      address: 'บ้านเลขที่ 123 หมู่ 1 ตำบลในเมือง อำเภอเมือง จังหวัดนครราชสีมา'
    },
    {
      child_name: 'เด็กหญิงสมหญิง ใจดี',
      mother_name: 'นางสมหญิง ใจดี',
      father_name: 'นายสมชาย ใจดี',
      weight: '2300',
      GA: '37',
      latitude: '14.9799',
      longitude: '102.0977',
      address: 'บ้านเลขที่ 456 หมู่ 2 ตำบลในเมือง อำเภอเมือง จังหวัดขอนแก่น',
      birth_date: '2024-12-01'
    },
    {
      child_name: 'เด็กชายสมชาย ใจดี',
      mother_name: 'นางสมใจ ใจดี',
      father_name: 'นายสมศักดิ์ ใจดี',
      weight: '3200',
      GA: '39',
      latitude: '18.7883',
      longitude: '98.9853',
      address: 'บ้านเลขที่ 789 หมู่ 3 ตำบลช้างเผือก อำเภอเมือง จังหวัดเชียงใหม่',
      birth_date: '2024-11-15'
    }
  ];
  
  sampleData.forEach(data => {
    handleAddRecord(data);
  });
  
  console.log('Sample data initialized');
}