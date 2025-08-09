#!/usr/bin/env python3
"""
BAIT Service - Dependency Checker
=================================

Script per verificare e installare dipendenze BAIT Service
Compatibile con Windows CMD/PowerShell

Autore: Franco - BAIT Service
"""

import sys
import subprocess
import importlib
from pathlib import Path


class DependencyChecker:
    """Checker per dipendenze BAIT Service"""
    
    def __init__(self):
        self.required_modules = {
            'dash': '2.17.0',
            'plotly': '5.17.0', 
            'pandas': '2.0.0',
            'chardet': '5.0.0',
            'openpyxl': '3.1.0'
        }
        
        self.optional_modules = {
            'xlsxwriter': '3.1.0',
            'reportlab': '4.0.0'
        }
    
    def check_python(self):
        """Verifica versione Python"""
        version = sys.version_info
        print(f"🐍 Python {version.major}.{version.minor}.{version.micro}")
        
        if version.major < 3 or (version.major == 3 and version.minor < 8):
            print("❌ Python 3.8+ richiesto")
            return False
        
        print("✅ Versione Python compatibile")
        return True
    
    def check_pip(self):
        """Verifica pip disponibile"""
        try:
            result = subprocess.run([sys.executable, '-m', 'pip', '--version'], 
                                  capture_output=True, text=True)
            if result.returncode == 0:
                print(f"✅ pip: {result.stdout.strip()}")
                return True
            else:
                print("❌ pip non disponibile")
                return False
        except Exception as e:
            print(f"❌ Errore controllo pip: {e}")
            return False
    
    def check_module(self, module_name, min_version=None):
        """Verifica singolo modulo"""
        try:
            module = importlib.import_module(module_name)
            
            if hasattr(module, '__version__'):
                version = module.__version__
                print(f"✅ {module_name}: {version}")
                return True
            else:
                print(f"✅ {module_name}: disponibile (version unknown)")
                return True
                
        except ImportError:
            print(f"❌ {module_name}: non installato")
            return False
    
    def install_module(self, module_name, version=None):
        """Installa modulo mancante"""
        try:
            if version:
                package = f"{module_name}>={version}"
            else:
                package = module_name
            
            print(f"🔧 Installazione {package}...")
            
            result = subprocess.run([
                sys.executable, '-m', 'pip', 'install', package
            ], capture_output=True, text=True)
            
            if result.returncode == 0:
                print(f"✅ {module_name} installato con successo")
                return True
            else:
                print(f"❌ Errore installazione {module_name}: {result.stderr}")
                return False
                
        except Exception as e:
            print(f"❌ Errore installazione {module_name}: {e}")
            return False
    
    def check_all_dependencies(self, install_missing=True):
        """Verifica tutte le dipendenze"""
        print("🔍 CONTROLLO DIPENDENZE BAIT SERVICE")
        print("=" * 50)
        
        # Check Python
        if not self.check_python():
            return False
        
        # Check pip
        if not self.check_pip():
            return False
        
        print()
        print("📦 MODULI RICHIESTI:")
        print("-" * 30)
        
        missing_modules = []
        
        # Check required modules
        for module, version in self.required_modules.items():
            if not self.check_module(module, version):
                missing_modules.append((module, version, True))  # True = required
        
        print()
        print("📦 MODULI OPZIONALI:")
        print("-" * 30)
        
        # Check optional modules
        for module, version in self.optional_modules.items():
            if not self.check_module(module, version):
                missing_modules.append((module, version, False))  # False = optional
        
        # Install missing modules if requested
        if missing_modules and install_missing:
            print()
            print("🔧 INSTALLAZIONE MODULI MANCANTI:")
            print("-" * 40)
            
            install_success = True
            
            for module, version, is_required in missing_modules:
                if is_required or input(f"Installare {module} (opzionale)? [y/N]: ").lower() == 'y':
                    if not self.install_module(module, version):
                        if is_required:
                            install_success = False
                        print()
            
            # Re-check required modules
            if install_success:
                print()
                print("🔍 VERIFICA POST-INSTALLAZIONE:")
                print("-" * 35)
                
                for module, version, is_required in missing_modules:
                    if is_required:
                        if not self.check_module(module, version):
                            install_success = False
        
            return install_success
        
        elif missing_modules:
            print()
            print("⚠️ MODULI MANCANTI RILEVATI:")
            required_missing = [m for m, v, req in missing_modules if req]
            if required_missing:
                print(f"❌ Richiesti: {', '.join(required_missing)}")
                return False
            else:
                print("✅ Tutti i moduli richiesti sono presenti")
                return True
        
        else:
            print()
            print("✅ TUTTE LE DIPENDENZE SONO SODDISFATTE!")
            return True
    
    def create_requirements_file(self):
        """Crea file requirements.txt"""
        requirements_path = Path("requirements_bait.txt")
        
        with open(requirements_path, 'w') as f:
            f.write("# BAIT Service - Requirements\n")
            f.write("# Generated by dependency checker\n\n")
            
            f.write("# Required modules\n")
            for module, version in self.required_modules.items():
                f.write(f"{module}>={version}\n")
            
            f.write("\n# Optional modules\n")
            for module, version in self.optional_modules.items():
                f.write(f"{module}>={version}\n")
        
        print(f"📄 File requirements creato: {requirements_path}")
    
    def check_bait_files(self):
        """Verifica files sistema BAIT Service"""
        print()
        print("🔍 CONTROLLO FILES SISTEMA BAIT SERVICE:")
        print("-" * 45)
        
        required_files = [
            "bait_dashboard_upload.py",
            "bait_simple_dashboard.py",
            "start_bait_system.bat"
        ]
        
        optional_files = [
            "bait_controller_v2.py",
            "business_rules_v2.py", 
            "alert_generator.py"
        ]
        
        missing_required = []
        
        for file in required_files:
            if Path(file).exists():
                print(f"✅ {file}")
            else:
                print(f"❌ {file} - MANCANTE")
                missing_required.append(file)
        
        for file in optional_files:
            if Path(file).exists():
                print(f"✅ {file} (opzionale)")
            else:
                print(f"⚠️ {file} (opzionale) - mancante")
        
        # Check directories
        upload_dir = Path("upload_csv")
        if upload_dir.exists():
            print(f"✅ Cartella upload_csv/")
        else:
            print(f"⚠️ Cartella upload_csv/ - sarà creata automaticamente")
            upload_dir.mkdir(exist_ok=True)
            print(f"✅ Cartella upload_csv/ creata")
        
        return len(missing_required) == 0


def main():
    """Funzione principale"""
    checker = DependencyChecker()
    
    try:
        # Check dependencies
        deps_ok = checker.check_all_dependencies(install_missing=True)
        
        # Check BAIT files
        files_ok = checker.check_bait_files()
        
        # Create requirements file
        checker.create_requirements_file()
        
        print()
        print("=" * 50)
        
        if deps_ok and files_ok:
            print("🎉 SISTEMA BAIT SERVICE PRONTO!")
            print()
            print("🚀 PROSSIMI PASSI:")
            print("   1. Esegui: start_bait_system.bat")
            print("   2. Apri: http://localhost:8051") 
            print("   3. Upload i 7 CSV nella dashboard")
            print()
            return True
        else:
            print("❌ PROBLEMI RILEVATI - Sistema non pronto")
            if not deps_ok:
                print("   • Dipendenze mancanti")
            if not files_ok:
                print("   • Files sistema mancanti")
            print()
            print("🔧 SOLUZIONI:")
            print("   • Esegui nuovamente questo script")
            print("   • Controlla log errori sopra")
            print("   • Verifica connessione internet")
            return False
            
    except KeyboardInterrupt:
        print("\n\n🛑 Controllo interrotto dall'utente")
        return False
    except Exception as e:
        print(f"\n❌ Errore inaspettato: {e}")
        return False


if __name__ == "__main__":
    success = main()
    
    if not success:
        input("\nPremi ENTER per chiudere...")
        sys.exit(1)
    else:
        input("\nPremi ENTER per chiudere...")
        sys.exit(0)