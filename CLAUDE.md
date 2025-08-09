/agent# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This repository contains a data management system for employee activity tracking, specifically designed for controlling and monitoring work activities in an Italian business environment. The system manages:

- **Employee activities** (`attivita.csv`) - Detailed work activities with tickets, clients, durations, and descriptions
- **Vehicle tracking** (`auto.csv`) - Company vehicle usage by employees including pickup/return times
- **Time tracking** (`timbrature.csv`) - Employee time clock data with start/end times, locations, and calculated hours
- **Permission management** (`permessi.csv`) - Leave requests and approvals (ferie, sick days, etc.)
- **Calendar scheduling** (`calendario.csv`) - Planned activities and assignments
- **TeamViewer logs** (`teamviewer_bait.csv`, `teamviewer_gruppo.csv`) - Remote access session tracking

## Data Structure

### Key CSV Files Structure:
- **attivita.csv**: Activity tracking with columns for contracts, tickets, start/end times, companies, activity types, descriptions, and durations
- **timbrature.csv**: Time clock data with employee names, client information, GPS coordinates, calculated hours in various formats
- **auto.csv**: Vehicle allocation tracking with employee assignments, vehicle models, and usage periods
- **permessi.csv**: Leave management with request dates, employee names, leave types, approval status
- **calendario.csv**: Resource scheduling and planning data
- **teamviewer_*.csv**: Remote session monitoring for security and productivity tracking

## Data Characteristics

- All CSV files use semicolon (`;`) as delimiter
- Date formats: DD/MM/YYYY HH:MM
- Italian language content for descriptions and employee data
- Contains sensitive employee information including names, locations, and work details
- Time calculations include both standard hours and decimal representations
- Geographic data includes Italian addresses and GPS coordinates

## Working with this Repository

When analyzing or processing this data:
1. Handle Italian date formats (DD/MM/YYYY)
2. Respect semicolon CSV delimiters
3. Be aware of mixed encoding issues (some files contain UTF-8 BOM)
4. Consider data privacy when working with employee information
5. Time calculations involve both standard time formats and decimal hour representations

## Data Analysis Commands

Since this is a data-only repository with CSV files, typical operations involve:
- CSV analysis using pandas, Excel, or similar tools
- Data validation and cleaning scripts
- Report generation from the tracked activities
- Cross-referencing between different tracking systems (time, activities, permissions)