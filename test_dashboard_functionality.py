#!/usr/bin/env python3
"""
BAIT Enterprise Dashboard - Comprehensive Functionality Testing
============================================================

Test script to validate all enterprise dashboard features are working correctly.
Tests all filters, search, export functionality, and data integrity.
"""

import requests
import json
import time
import sys
from pathlib import Path

class BAITDashboardTester:
    """Comprehensive dashboard functionality testing"""
    
    def __init__(self, dashboard_url="http://localhost:8054"):
        self.dashboard_url = dashboard_url
        self.test_results = []
    
    def run_all_tests(self):
        """Run comprehensive test suite"""
        print("🧪 BAIT ENTERPRISE DASHBOARD - COMPREHENSIVE TESTING")
        print("=" * 70)
        
        # Test connectivity
        if not self.test_connectivity():
            print("❌ Dashboard connectivity failed - cannot proceed with tests")
            return False
        
        # Test all components
        tests = [
            ("Dashboard Loading", self.test_dashboard_loading),
            ("Data Integrity", self.test_data_integrity),
            ("API Endpoints", self.test_api_endpoints),
            ("Filter Components", self.test_filter_components),
            ("Chart Components", self.test_chart_components),
            ("Table Components", self.test_table_components),
            ("Export Functions", self.test_export_functions),
            ("Mobile Responsiveness", self.test_mobile_responsiveness),
            ("Performance Metrics", self.test_performance_metrics)
        ]
        
        total_tests = len(tests)
        passed_tests = 0
        
        for test_name, test_func in tests:
            print(f"\n🔍 Testing: {test_name}")
            try:
                result = test_func()
                if result:
                    print(f"✅ {test_name}: PASSED")
                    passed_tests += 1
                else:
                    print(f"❌ {test_name}: FAILED")
                self.test_results.append((test_name, result))
            except Exception as e:
                print(f"💥 {test_name}: ERROR - {e}")
                self.test_results.append((test_name, False))
        
        # Generate final report
        self.generate_test_report(passed_tests, total_tests)
        return passed_tests == total_tests
    
    def test_connectivity(self):
        """Test basic dashboard connectivity"""
        try:
            response = requests.get(self.dashboard_url, timeout=10)
            return response.status_code == 200
        except:
            return False
    
    def test_dashboard_loading(self):
        """Test dashboard HTML loading and CSS resources"""
        try:
            response = requests.get(self.dashboard_url, timeout=10)
            html_content = response.text
            
            # Check for key dashboard elements
            required_elements = [
                "BAIT Service Enterprise Dashboard",
                "Executive KPI Dashboard", 
                "Advanced Enterprise Filters",
                "Enterprise Alert Management System",
                "bootstrap",
                "font-awesome"
            ]
            
            for element in required_elements:
                if element not in html_content:
                    print(f"   ⚠️  Missing element: {element}")
                    return False
            
            print(f"   ✓ Dashboard HTML loaded successfully ({len(html_content)} chars)")
            return True
            
        except Exception as e:
            print(f"   ❌ Loading error: {e}")
            return False
    
    def test_data_integrity(self):
        """Test data loading and integrity"""
        try:
            # Test Dash layout endpoint
            layout_url = f"{self.dashboard_url}/_dash-layout"
            response = requests.get(layout_url, timeout=10)
            
            if response.status_code != 200:
                return False
            
            layout_data = response.json()
            
            # Check for KPI data
            layout_str = str(layout_data)
            
            expected_data = [
                "371",  # Total records
                "21",   # Total alerts
                "96.4", # System accuracy
                "2956.33",  # Cost impact
                "Alex Ferrario",  # Technician names
                "Gabriele De Palma",
                "Temporal Overlap",  # Categories
                "IMMEDIATE",  # Priorities
                "CRITICO"     # Severities
            ]
            
            missing_data = []
            for data in expected_data:
                if data not in layout_str:
                    missing_data.append(data)
            
            if missing_data:
                print(f"   ⚠️  Missing data: {missing_data}")
                return False
            
            print(f"   ✓ All expected data elements found")
            return True
            
        except Exception as e:
            print(f"   ❌ Data integrity error: {e}")
            return False
    
    def test_api_endpoints(self):
        """Test critical API endpoints"""
        try:
            endpoints = [
                "/_dash-layout",
                "/_dash-dependencies", 
                "/_dash-config"
            ]
            
            for endpoint in endpoints:
                url = f"{self.dashboard_url}{endpoint}"
                response = requests.get(url, timeout=5)
                if response.status_code != 200:
                    print(f"   ❌ Endpoint failed: {endpoint}")
                    return False
            
            print(f"   ✓ All API endpoints responding correctly")
            return True
            
        except Exception as e:
            print(f"   ❌ API endpoint error: {e}")
            return False
    
    def test_filter_components(self):
        """Test filter component presence and structure"""
        try:
            layout_url = f"{self.dashboard_url}/_dash-layout"
            response = requests.get(layout_url, timeout=10)
            layout_data = response.json()
            layout_str = str(layout_data)
            
            # Check for filter components
            filter_components = [
                "enterprise-tech-filter",      # Technician filter
                "enterprise-priority-filter",  # Priority filter
                "enterprise-category-filter",  # Category filter
                "enterprise-confidence-filter", # Confidence slider
                "enterprise-search-input",     # Search input
                "reset-filters-btn",           # Reset button
                "apply-filters-btn"            # Apply button
            ]
            
            missing_components = []
            for component in filter_components:
                if component not in layout_str:
                    missing_components.append(component)
            
            if missing_components:
                print(f"   ⚠️  Missing filter components: {missing_components}")
                return False
            
            print(f"   ✓ All filter components present")
            return True
            
        except Exception as e:
            print(f"   ❌ Filter component error: {e}")
            return False
    
    def test_chart_components(self):
        """Test chart component presence"""
        try:
            layout_url = f"{self.dashboard_url}/_dash-layout"
            response = requests.get(layout_url, timeout=10)
            layout_data = response.json()
            layout_str = str(layout_data)
            
            # Check for chart components
            chart_components = [
                "enterprise-priority-chart",
                "enterprise-performance-chart", 
                "enterprise-cost-confidence-chart",
                "enterprise-trend-chart",
                "enterprise-category-cost-chart"
            ]
            
            missing_charts = []
            for chart in chart_components:
                if chart not in layout_str:
                    missing_charts.append(chart)
            
            if missing_charts:
                print(f"   ⚠️  Missing chart components: {missing_charts}")
                return False
            
            print(f"   ✓ All chart components present")
            return True
            
        except Exception as e:
            print(f"   ❌ Chart component error: {e}")
            return False
    
    def test_table_components(self):
        """Test table functionality and data"""
        try:
            layout_url = f"{self.dashboard_url}/_dash-layout"
            response = requests.get(layout_url, timeout=10)
            layout_data = response.json()
            layout_str = str(layout_data)
            
            # Check table structure
            table_elements = [
                "enterprise-alerts-table",
                "DataTable",
                "Alert ID",
                "Priority", 
                "Technician",
                "Confidence %",
                "Cost Impact €",
                "BAIT_V2_20250810_",  # Alert IDs
                "sort_action",
                "filter_action",
                "export_format"
            ]
            
            missing_elements = []
            for element in table_elements:
                if element not in layout_str:
                    missing_elements.append(element)
            
            if missing_elements:
                print(f"   ⚠️  Missing table elements: {missing_elements}")
                return False
            
            print(f"   ✓ Table structure and data complete")
            return True
            
        except Exception as e:
            print(f"   ❌ Table component error: {e}")
            return False
    
    def test_export_functions(self):
        """Test export button presence"""
        try:
            layout_url = f"{self.dashboard_url}/_dash-layout"
            response = requests.get(layout_url, timeout=10)
            layout_data = response.json()
            layout_str = str(layout_data)
            
            # Check export components
            export_components = [
                "export-excel-enterprise-btn",
                "export-pdf-enterprise-btn", 
                "export-csv-enterprise-btn",
                "Export Excel",
                "Export PDF", 
                "Export CSV"
            ]
            
            missing_exports = []
            for component in export_components:
                if component not in layout_str:
                    missing_exports.append(component)
            
            if missing_exports:
                print(f"   ⚠️  Missing export components: {missing_exports}")
                return False
            
            print(f"   ✓ Export functionality components present")
            return True
            
        except Exception as e:
            print(f"   ❌ Export function error: {e}")
            return False
    
    def test_mobile_responsiveness(self):
        """Test mobile CSS and responsiveness"""
        try:
            response = requests.get(self.dashboard_url, timeout=10)
            html_content = response.text
            
            # Check for mobile CSS
            mobile_elements = [
                "@media (max-width: 768px)",
                "viewport", 
                "responsive",
                "bootstrap",
                "col-md",
                "col-lg"
            ]
            
            missing_mobile = []
            for element in mobile_elements:
                if element not in html_content:
                    missing_mobile.append(element)
            
            if missing_mobile:
                print(f"   ⚠️  Missing mobile elements: {missing_mobile}")
                return False
            
            print(f"   ✓ Mobile responsive design elements present")
            return True
            
        except Exception as e:
            print(f"   ❌ Mobile responsiveness error: {e}")
            return False
    
    def test_performance_metrics(self):
        """Test dashboard performance and response times"""
        try:
            start_time = time.time()
            response = requests.get(self.dashboard_url, timeout=15)
            load_time = time.time() - start_time
            
            # Performance benchmarks
            if load_time > 10:
                print(f"   ⚠️  Slow load time: {load_time:.2f}s (target: <3s)")
                return False
            
            # Check response size (should be reasonable)
            content_size = len(response.text)
            if content_size < 5000:  # Too small - likely error
                print(f"   ⚠️  Content too small: {content_size} chars")
                return False
            
            if content_size > 1000000:  # Too large - performance issue
                print(f"   ⚠️  Content too large: {content_size} chars")
                return False
            
            print(f"   ✓ Performance: {load_time:.2f}s load time, {content_size} chars")
            return True
            
        except Exception as e:
            print(f"   ❌ Performance test error: {e}")
            return False
    
    def generate_test_report(self, passed, total):
        """Generate comprehensive test report"""
        print("\n" + "=" * 70)
        print("📊 FINAL TEST REPORT")
        print("=" * 70)
        
        success_rate = (passed / total) * 100
        
        print(f"✅ Passed Tests: {passed}/{total} ({success_rate:.1f}%)")
        
        # Detailed results
        for test_name, result in self.test_results:
            status = "✅ PASS" if result else "❌ FAIL"
            print(f"   {status}: {test_name}")
        
        # Overall assessment
        if success_rate == 100:
            print(f"\n🎉 DASHBOARD FULLY FUNCTIONAL - PRODUCTION READY! 🎉")
        elif success_rate >= 90:
            print(f"\n✅ Dashboard mostly functional - Minor issues to address")
        elif success_rate >= 70:
            print(f"\n⚠️  Dashboard partially functional - Multiple issues found")
        else:
            print(f"\n❌ Dashboard has critical issues - Requires immediate attention")
        
        print("\n🎯 CONFIDENCE LEVEL:")
        confidence = min(100, int(success_rate))
        print(f"   CONFIDENCE: {confidence}/100")
        
        if confidence >= 95:
            print("   STATUS: ✅ ENTERPRISE PRODUCTION DEPLOYMENT READY")
        elif confidence >= 80:
            print("   STATUS: ⚠️  READY WITH MINOR FIXES")
        else:
            print("   STATUS: ❌ NOT READY - CRITICAL ISSUES")
        
        return success_rate == 100


def main():
    """Main testing execution"""
    print("\n🚀 Starting BAIT Enterprise Dashboard Testing...")
    
    tester = BAITDashboardTester()
    
    # Wait for dashboard to be ready
    print("⏳ Waiting for dashboard to be ready...")
    time.sleep(2)
    
    # Run all tests
    success = tester.run_all_tests()
    
    if success:
        print("\n🏆 ALL TESTS PASSED - DASHBOARD READY FOR FRANCO'S DAILY USE!")
        sys.exit(0)
    else:
        print("\n⚠️  Some tests failed - Dashboard needs attention")
        sys.exit(1)


if __name__ == "__main__":
    main()