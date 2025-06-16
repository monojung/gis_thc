// backend/server.js
const express = require('express');
const cors = require('cors');
const { google } = require('googleapis');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3001;

// Middleware
app.use(cors());
app.use(express.json());

// Google Sheets configuration
const SPREADSHEET_ID = '1JQ_vWiRMsZwZyNVim_v89u_o1S1isWYEGYie57d0PH0';
const RANGE = 'Sheet1!A:K'; // Adjust range as needed

// Google Auth
const auth = new google.auth.GoogleAuth({
  keyFile: process.env.GOOGLE_SERVICE_ACCOUNT_KEY_FILE, // Path to service account key
  scopes: ['https://www.googleapis.com/auth/spreadsheets'],
});

const sheets = google.sheets({ version: 'v4', auth });

// Helper function to convert array to object
const arrayToObject = (headers, row) => {
  const obj = {};
  headers.forEach((header, index) => {
    obj[header] = row[index] || '';
  });
  return obj;
};

// Routes

// GET /api/data - Get all health data
app.get('/api/data', async (req, res) => {
  try {
    const result = await sheets.spreadsheets.values.get({
      spreadsheetId: SPREADSHEET_ID,
      range: RANGE,
    });

    const rows = result.data.values;
    if (!rows || rows.length === 0) {
      return res.json({ success: true, data: [] });
    }

    // First row contains headers
    const headers = rows[0];
    const dataRows = rows.slice(1);

    // Convert to objects
    const data = dataRows.map(row => arrayToObject(headers, row));

    res.json({ success: true, data });
  } catch (error) {
    console.error('Error fetching data:', error);
    res.status(500).json({ 
      success: false, 
      message: 'Failed to fetch data',
      error: error.message 
    });
  }
});

// POST /api/data - Add new record
app.post('/api/data', async (req, res) => {
  try {
    const { data } = req.body;
    
    // Validate required fields
    if (!data.latitude || !data.longitude) {
      return res.status(400).json({
        success: false,
        message: 'Latitude and longitude are required'
      });
    }

    // Get current data to determine headers
    const result = await sheets.spreadsheets.values.get({
      spreadsheetId: SPREADSHEET_ID,
      range: 'Sheet1!1:1',
    });

    let headers = [];
    if (result.data.values && result.data.values.length > 0) {
      headers = result.data.values[0];
    } else {
      // If no headers exist, create them
      headers = [
        'id', 'child_name', 'mother_name', 'father_name', 
        'weight', 'GA', 'latitude', 'longitude', 
        'address', 'birth_date', 'expected_date'
      ];
      
      // Add headers to sheet
      await sheets.spreadsheets.values.update({
        spreadsheetId: SPREADSHEET_ID,
        range: 'Sheet1!1:1',
        valueInputOption: 'RAW',
        resource: { values: [headers] },
      });
    }

    // Create new row data
    const newRow = [];
    headers.forEach(header => {
      if (header === 'id') {
        // Generate simple ID based on timestamp
        newRow.push(Date.now().toString());
      } else {
        newRow.push(data[header] || '');
      }
    });

    // Append new row
    await sheets.spreadsheets.values.append({
      spreadsheetId: SPREADSHEET_ID,
      range: 'Sheet1!A:K',
      valueInputOption: 'RAW',
      resource: { values: [newRow] },
    });

    res.json({ 
      success: true, 
      message: 'Record added successfully',
      id: newRow[0]
    });

  } catch (error) {
    console.error('Error adding record:', error);
    res.status(500).json({ 
      success: false, 
      message: 'Failed to add record',
      error: error.message 
    });
  }
});

// PUT /api/data/:id - Update existing record
app.put('/api/data/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { data } = req.body;

    // Get all data to find the row to update
    const result = await sheets.spreadsheets.values.get({
      spreadsheetId: SPREADSHEET_ID,
      range: RANGE,
    });

    const rows = result.data.values;
    if (!rows || rows.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'No data found'
      });
    }

    const headers = rows[0];
    const dataRows = rows.slice(1);

    // Find row index by ID
    const rowIndex = dataRows.findIndex(row => row[0] === id);
    if (rowIndex === -1) {
      return res.status(404).json({
        success: false,
        message: 'Record not found'
      });
    }

    // Update row data
    const updatedRow = [];
    headers.forEach(header => {
      if (header === 'id') {
        updatedRow.push(id);
      } else {
        updatedRow.push(data[header] || '');
      }
    });

    // Update the specific row (add 2 to account for header row and 0-based index)
    const updateRange = `Sheet1!A${rowIndex + 2}:K${rowIndex + 2}`;
    await sheets.spreadsheets.values.update({
      spreadsheetId: SPREADSHEET_ID,
      range: updateRange,
      valueInputOption: 'RAW',
      resource: { values: [updatedRow] },
    });

    res.json({ 
      success: true, 
      message: 'Record updated successfully'
    });

  } catch (error) {
    console.error('Error updating record:', error);
    res.status(500).json({ 
      success: false, 
      message: 'Failed to update record',
      error: error.message 
    });
  }
});

// DELETE /api/data/:id - Delete record
app.delete('/api/data/:id', async (req, res) => {
  try {
    const { id } = req.params;

    // Get all data to find the row to delete
    const result = await sheets.spreadsheets.values.get({
      spreadsheetId: SPREADSHEET_ID,
      range: RANGE,
    });

    const rows = result.data.values;
    if (!rows || rows.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'No data found'
      });
    }

    const dataRows = rows.slice(1);
    const rowIndex = dataRows.findIndex(row => row[0] === id);
    
    if (rowIndex === -1) {
      return res.status(404).json({
        success: false,
        message: 'Record not found'
      });
    }

    // Delete row (add 2 to account for header row and 0-based index)
    const deleteRowIndex = rowIndex + 2;
    
    await sheets.spreadsheets.batchUpdate({
      spreadsheetId: SPREADSHEET_ID,
      resource: {
        requests: [{
          deleteDimension: {
            range: {
              sheetId: 0, // Assuming first sheet
              dimension: 'ROWS',
              startIndex: deleteRowIndex - 1,
              endIndex: deleteRowIndex
            }
          }
        }]
      }
    });

    res.json({ 
      success: true, 
      message: 'Record deleted successfully'
    });

  } catch (error) {
    console.error('Error deleting record:', error);
    res.status(500).json({ 
      success: false, 
      message: 'Failed to delete record',
      error: error.message 
    });
  }
});

// GET /api/stats - Get statistics
app.get('/api/stats', async (req, res) => {
  try {
    const result = await sheets.spreadsheets.values.get({
      spreadsheetId: SPREADSHEET_ID,
      range: RANGE,
    });

    const rows = result.data.values;
    if (!rows || rows.length === 0) {
      return res.json({ 
        success: true, 
        stats: { total: 0, pregnant: 0, lowWeight: 0, normalWeight: 0 }
      });
    }

    const headers = rows[0];
    const dataRows = rows.slice(1);
    const data = dataRows.map(row => arrayToObject(headers, row));

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

    res.json({ 
      success: true, 
      stats: { total, pregnant, lowWeight, normalWeight }
    });

  } catch (error) {
    console.error('Error fetching stats:', error);
    res.status(500).json({ 
      success: false, 
      message: 'Failed to fetch statistics',
      error: error.message 
    });
  }
});

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({ 
    success: true, 
    message: 'API is running',
    timestamp: new Date().toISOString()
  });
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({
    success: false,
    message: 'Something went wrong!',
    error: process.env.NODE_ENV === 'development' ? err.message : 'Internal server error'
  });
});

// 404 handler
app.use('*', (req, res) => {
  res.status(404).json({
    success: false,
    message: 'API endpoint not found'
  });
});

app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`);
  console.log(`Health check: http://localhost:${PORT}/api/health`);
});