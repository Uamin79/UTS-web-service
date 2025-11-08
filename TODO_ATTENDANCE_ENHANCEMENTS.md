# TODO: Enhance Attendance Management Features in guru.php

## 1. Attendance Summary per Student
- [ ] Add new "Attendance Summary" tab in sidebar navigation
- [ ] Create summary section with filters (class, subject, date range)
- [ ] Display statistics table: Student Name, NIS, Total Days, Present/Sick/Permit/Absent counts, Attendance %
- [ ] Add summary chart using Chart.js (pie chart for overall attendance distribution)
- [ ] Implement loadAttendanceSummary() function to fetch and display data

## 2. Quick Attendance Input
- [ ] Add "Mark All Present" button above attendance input table
- [ ] Change status dropdowns to checkboxes with "Present" default
- [ ] Add toggle functionality for marking students absent
- [ ] Update saveAttendance() to handle checkbox inputs

## 3. Enhanced History Filters
- [ ] Add "Student Name/NIS" input field to attendance history filters
- [ ] Update loadAttendanceHistory() to include name/NIS filter in API call
- [ ] Add search functionality with debouncing

## 4. Attendance Calendar
- [ ] Add new "Attendance Calendar" tab in sidebar
- [ ] Implement monthly calendar view with attendance indicators
- [ ] Add calendar navigation (previous/next month)
- [ ] Color-code days: green (good attendance), yellow (mixed), red (poor attendance)
- [ ] Click on date to show attendance details modal
- [ ] Add calendar-specific filters (class, subject)

## 5. Excel Import
- [ ] Add "Import from Excel" section in attendance input tab
- [ ] Create file upload form for CSV files
- [ ] Add CSV format instructions (NIS, Status, Notes columns)
- [ ] Implement client-side CSV parsing and validation
- [ ] Add server-side import handler with validation
- [ ] Show import results (success/error counts)

## 6. Low Attendance Notifications
- [ ] Add attendance alerts section at top of attendance tab
- [ ] Calculate attendance percentage for each student in teacher's classes
- [ ] Display warning for students with <75% attendance
- [ ] Show list of problematic students with their attendance %
- [ ] Add "View Details" link to attendance summary

## Testing & Validation
- [ ] Test all new features with sample data
- [ ] Verify data accuracy and calculations
- [ ] Check UI responsiveness and consistency
- [ ] Test edge cases (empty data, invalid inputs, large datasets)
- [ ] Validate permission checks and security
