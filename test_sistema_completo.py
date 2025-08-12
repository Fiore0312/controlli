#!/usr/bin/env python3
"""
BAIT Service - Test Sistema Completo
====================================

Test sistematico di tutti i componenti del sistema enterprise BAIT.
Verifica che tutti i criteri di successo siano soddisfatti.

Versione: Test Enterprise 1.0
Autore: Franco - BAIT Service
"""

import os
import sys
import json
import time
import subprocess
import importlib.util
from pathlib import Path
from datetime import datetime
import logging

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


class BAITSystemTester:
    """Sistema di test completo per BAIT Enterprise"""
    
    def __init__(self):
        self.base_dir = Path(".")
        self.test_results = {
            'start_time': datetime.now(),
            'tests_run': 0,
            'tests_passed': 0,
            'tests_failed': 0,
            'details': []
        }
        
        logger.info("🧪 BAIT System Tester initialized")
    
    def log_test_result(self, test_name: str, passed: bool, message: str = ""):
        """Log test result"""
        self.test_results['tests_run'] += 1
        
        if passed:
            self.test_results['tests_passed'] += 1
            status = "✅ PASS"
        else:
            self.test_results['tests_failed'] += 1
            status = "❌ FAIL"
        
        result = {
            'test': test_name,
            'status': status,
            'message': message,
            'timestamp': datetime.now().isoformat()
        }
        
        self.test_results['details'].append(result)
        logger.info(f"{status} - {test_name}: {message}")
    
    def test_file_structure(self):
        """Test 1: Verifica struttura file sistema"""
        logger.info("\n🏗️ TEST 1: File Structure Validation")
        
        required_files = [
            'start_bait_enterprise.bat',
            'requirements.txt',
            'bait_controller_enterprise.py',
            'bait_dashboard_final.py'
        ]
        
        for file_name in required_files:
            file_path = self.base_dir / file_name
            if file_path.exists():
                self.log_test_result(f"File exists: {file_name}", True, f"Size: {file_path.stat().st_size} bytes")
            else:
                self.log_test_result(f"File exists: {file_name}", False, "File not found")
        
        # Test directory structure
        required_dirs = ['data/input', 'data/processed', 'logs', 'upload_csv']
        for dir_name in required_dirs:
            dir_path = self.base_dir / dir_name
            if dir_path.exists():
                self.log_test_result(f"Directory exists: {dir_name}", True, "Directory found")
            else:
                self.log_test_result(f"Directory exists: {dir_name}", False, "Directory missing")
    
    def test_csv_data_files(self):
        """Test 2: Verifica file CSV dati"""
        logger.info("\n📊 TEST 2: CSV Data Files Validation")
        
        input_dir = self.base_dir / "data" / "input"
        required_csv_files = [
            'attivita.csv', 'timbrature.csv', 'teamviewer_bait.csv',
            'teamviewer_gruppo.csv', 'permessi.csv', 'auto.csv', 'calendario.csv'
        ]
        
        csv_count = 0
        total_records = 0
        
        for csv_file in required_csv_files:
            csv_path = input_dir / csv_file
            if csv_path.exists():
                try:
                    # Try to count lines (estimate records)
                    with open(csv_path, 'r', encoding='utf-8') as f:
                        lines = len(f.readlines())
                    records = max(0, lines - 1)  # Subtract header
                    total_records += records
                    csv_count += 1
                    self.log_test_result(f"CSV readable: {csv_file}", True, f"{records} records")
                except Exception as e:
                    self.log_test_result(f"CSV readable: {csv_file}", False, f"Error: {e}")
            else:
                self.log_test_result(f"CSV exists: {csv_file}", False, "File not found")
        
        self.log_test_result("CSV Data Summary", True, f"{csv_count}/7 files, {total_records} total records")
    
    def test_python_dependencies(self):
        """Test 3: Verifica dipendenze Python"""
        logger.info("\n🐍 TEST 3: Python Dependencies Validation")
        
        critical_imports = [
            ('pandas', 'Data processing'),
            ('dash', 'Dashboard framework'),
            ('plotly', 'Visualization'),
            ('chardet', 'Encoding detection')
        ]
        
        for module_name, description in critical_imports:
            try:
                importlib.import_module(module_name)
                self.log_test_result(f"Import {module_name}", True, description)
            except ImportError as e:
                self.log_test_result(f"Import {module_name}", False, f"Import error: {e}")
    
    def test_backend_controller(self):
        """Test 4: Test controller backend"""
        logger.info("\n⚙️ TEST 4: Backend Controller Validation")
        
        controller_file = self.base_dir / "bait_controller_enterprise.py"
        
        if not controller_file.exists():
            self.log_test_result("Controller exists", False, "Controller file not found")
            return
        
        try:
            # Test controller execution
            result = subprocess.run([
                sys.executable, str(controller_file)
            ], capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0:
                self.log_test_result("Controller execution", True, "Controller ran successfully")
                
                # Check if JSON result was created
                json_files = list(self.base_dir.glob("bait_results_v2_*.json"))
                if json_files:
                    latest_file = max(json_files, key=os.path.getctime)
                    self.log_test_result("JSON generation", True, f"Created: {latest_file.name}")
                    
                    # Validate JSON content
                    try:
                        with open(latest_file, 'r', encoding='utf-8') as f:
                            data = json.load(f)
                        
                        required_keys = ['metadata', 'kpis_v2', 'alerts_v2']
                        for key in required_keys:
                            if key in data:
                                self.log_test_result(f"JSON structure: {key}", True, "Key present")
                            else:
                                self.log_test_result(f"JSON structure: {key}", False, "Key missing")
                    
                    except Exception as e:
                        self.log_test_result("JSON validation", False, f"JSON error: {e}")
                else:
                    self.log_test_result("JSON generation", False, "No JSON file created")
            else:
                self.log_test_result("Controller execution", False, f"Exit code: {result.returncode}")
                
        except subprocess.TimeoutExpired:
            self.log_test_result("Controller execution", False, "Execution timeout")
        except Exception as e:
            self.log_test_result("Controller execution", False, f"Error: {e}")
    
    def test_dashboard_startup(self):
        """Test 5: Test dashboard startup"""
        logger.info("\n🌐 TEST 5: Dashboard Startup Validation")
        
        dashboard_file = self.base_dir / "bait_dashboard_final.py"
        
        if not dashboard_file.exists():
            self.log_test_result("Dashboard exists", False, "Dashboard file not found")
            return
        
        try:
            # Test dashboard import (syntax check)
            spec = importlib.util.spec_from_file_location("bait_dashboard_final", dashboard_file)
            module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(module)
            
            self.log_test_result("Dashboard import", True, "Module loaded successfully")
            
            # Test dashboard instantiation
            dashboard = module.BAITDashboardFinal()
            self.log_test_result("Dashboard instantiation", True, "Dashboard object created")
            
            # Check dashboard data
            if hasattr(dashboard, 'data') and dashboard.data:
                alerts_count = len(dashboard.data.get('alerts', []))
                self.log_test_result("Dashboard data", True, f"{alerts_count} alerts loaded")
            else:
                self.log_test_result("Dashboard data", False, "No data loaded")
                
        except Exception as e:
            self.log_test_result("Dashboard startup", False, f"Error: {e}")
    
    def test_performance_benchmarks(self):
        """Test 6: Performance benchmarks"""
        logger.info("\n⚡ TEST 6: Performance Benchmarks")
        
        # Test controller performance
        start_time = time.time()
        try:
            result = subprocess.run([
                sys.executable, 'bait_controller_enterprise.py'
            ], capture_output=True, text=True, timeout=10)
            
            processing_time = time.time() - start_time
            
            if processing_time < 3.0:  # Under 3 seconds target
                self.log_test_result("Controller performance", True, f"{processing_time:.2f}s (target: <3s)")
            else:
                self.log_test_result("Controller performance", False, f"{processing_time:.2f}s (target: <3s)")
                
        except Exception as e:
            self.log_test_result("Controller performance", False, f"Error: {e}")
    
    def test_startup_script(self):
        """Test 7: Windows startup script validation"""
        logger.info("\n🚀 TEST 7: Startup Script Validation")
        
        startup_script = self.base_dir / "start_bait_enterprise.bat"
        
        if startup_script.exists():
            self.log_test_result("Startup script exists", True, "start_bait_enterprise.bat found")
            
            # Check script content
            try:
                with open(startup_script, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                required_sections = [
                    'FASE 1', 'FASE 2', 'FASE 3', 'FASE 4', 'FASE 5', 'FASE 6'
                ]
                
                for section in required_sections:
                    if section in content:
                        self.log_test_result(f"Script section: {section}", True, "Section found")
                    else:
                        self.log_test_result(f"Script section: {section}", False, "Section missing")
                        
            except Exception as e:
                self.log_test_result("Startup script content", False, f"Error: {e}")
        else:
            self.log_test_result("Startup script exists", False, "Script not found")
    
    def test_success_criteria(self):
        """Test 8: Success criteria validation"""
        logger.info("\n🎯 TEST 8: Success Criteria Validation")
        
        # Criterion 1: One-click startup
        if (self.base_dir / "start_bait_enterprise.bat").exists():
            self.log_test_result("One-click startup", True, "BAT file ready for Windows")
        else:
            self.log_test_result("One-click startup", False, "BAT file missing")
        
        # Criterion 2: Dashboard functionality
        json_files = list(self.base_dir.glob("bait_results_v2_*.json"))
        dashboard_file = self.base_dir / "bait_dashboard_final.py"
        
        if json_files and dashboard_file.exists():
            self.log_test_result("Dashboard functionality", True, "Data + Dashboard present")
        else:
            self.log_test_result("Dashboard functionality", False, "Missing components")
        
        # Criterion 3: CSV processing
        csv_files = list((self.base_dir / "data" / "input").glob("*.csv"))
        if len(csv_files) >= 5:  # At least 5 CSV files
            self.log_test_result("CSV processing capability", True, f"{len(csv_files)} CSV files available")
        else:
            self.log_test_result("CSV processing capability", False, f"Only {len(csv_files)} CSV files")
        
        # Criterion 4: Zero configuration
        required_enterprise_files = [
            "bait_controller_enterprise.py",
            "bait_dashboard_final.py", 
            "requirements.txt",
            "start_bait_enterprise.bat"
        ]
        
        all_present = all((self.base_dir / f).exists() for f in required_enterprise_files)
        if all_present:
            self.log_test_result("Zero configuration", True, "All enterprise files present")
        else:
            self.log_test_result("Zero configuration", False, "Missing enterprise files")
    
    def run_all_tests(self):
        """Run all tests and generate report"""
        
        print("=" * 80)
        print("🧪 BAIT SERVICE - ENTERPRISE SYSTEM TEST")
        print("=" * 80)
        print(f"Test Start Time: {self.test_results['start_time']}")
        print()
        
        # Run all tests
        self.test_file_structure()
        self.test_csv_data_files()
        self.test_python_dependencies()
        self.test_backend_controller()
        self.test_dashboard_startup()
        self.test_performance_benchmarks()
        self.test_startup_script()
        self.test_success_criteria()
        
        # Generate summary
        self.generate_test_report()
    
    def generate_test_report(self):
        """Generate comprehensive test report"""
        
        end_time = datetime.now()
        duration = end_time - self.test_results['start_time']
        
        print("\n" + "=" * 80)
        print("📊 TEST RESULTS SUMMARY")
        print("=" * 80)
        
        print(f"🕐 Total Duration: {duration}")
        print(f"🧪 Tests Run: {self.test_results['tests_run']}")
        print(f"✅ Tests Passed: {self.test_results['tests_passed']}")
        print(f"❌ Tests Failed: {self.test_results['tests_failed']}")
        
        success_rate = (self.test_results['tests_passed'] / self.test_results['tests_run']) * 100
        print(f"📈 Success Rate: {success_rate:.1f}%")
        
        print("\n🎯 ENTERPRISE READINESS STATUS:")
        if success_rate >= 90:
            print("🟢 READY FOR PRODUCTION - Enterprise criteria met!")
        elif success_rate >= 75:
            print("🟡 NEARLY READY - Minor issues to address")
        else:
            print("🔴 NOT READY - Significant issues require fixing")
        
        print("\n📋 DETAILED RESULTS:")
        for result in self.test_results['details']:
            print(f"  {result['status']} {result['test']}: {result['message']}")
        
        # Save report to file
        report_file = self.base_dir / f"test_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        with open(report_file, 'w', encoding='utf-8') as f:
            json.dump({
                **self.test_results,
                'end_time': end_time.isoformat(),
                'duration_seconds': duration.total_seconds(),
                'success_rate': success_rate
            }, f, indent=2, ensure_ascii=False, default=str)
        
        print(f"\n💾 Test report saved: {report_file}")
        print("=" * 80)
        
        return success_rate >= 90


def main():
    """Main test execution"""
    
    try:
        tester = BAITSystemTester()
        enterprise_ready = tester.run_all_tests()
        
        if enterprise_ready:
            print("\n🎉 CONGRATULATIONS! System is enterprise-ready!")
            return 0
        else:
            print("\n⚠️ System needs improvements before enterprise deployment")
            return 1
            
    except Exception as e:
        print(f"\n❌ Test execution failed: {e}")
        return 2


if __name__ == "__main__":
    sys.exit(main())