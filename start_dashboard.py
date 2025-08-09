#!/usr/bin/env python3
"""
BAIT Service - Launcher Dashboard Controller
==========================================

Script di avvio per Dashboard Controller Enterprise-Grade
Integra tutti i 4 agenti BAIT Service per controllo quotidiano.

Usage:
  python start_dashboard.py
  
Poi aprire: http://localhost:8050

Autore: Franco - BAIT Service  
"""

import sys
import subprocess
import os
from pathlib import Path

def check_dependencies():
    """Verifica dipendenze dashboard"""
    required_modules = [
        'dash', 'plotly', 'pandas', 'dash_bootstrap_components'
    ]
    
    missing = []
    for module in required_modules:
        try:
            __import__(module)
        except ImportError:
            missing.append(module)
    
    return missing

def install_dependencies(missing_modules):
    """Installa dipendenze mancanti"""
    if not missing_modules:
        return True
        
    print(f"🔧 Installazione dipendenze mancanti: {', '.join(missing_modules)}")
    
    try:
        # Prova con requirements file
        requirements_file = Path(__file__).parent / "requirements_dashboard.txt"
        if requirements_file.exists():
            subprocess.check_call([
                sys.executable, "-m", "pip", "install", 
                "-r", str(requirements_file)
            ])
        else:
            # Installa moduli base
            for module in missing_modules:
                subprocess.check_call([
                    sys.executable, "-m", "pip", "install", module
                ])
        
        print("✅ Dipendenze installate con successo!")
        return True
        
    except subprocess.CalledProcessError as e:
        print(f"❌ Errore installazione dipendenze: {e}")
        return False

def main():
    """Funzione principale launcher"""
    print("🚀 BAIT Service Dashboard Controller")
    print("=" * 50)
    
    # Verifica dipendenze
    print("🔍 Verifica dipendenze...")
    missing = check_dependencies()
    
    if missing:
        print(f"⚠️ Dipendenze mancanti: {', '.join(missing)}")
        if input("Installare automaticamente? (y/n): ").lower() == 'y':
            if not install_dependencies(missing):
                print("❌ Impossibile installare dipendenze")
                return False
        else:
            print("📋 Installa manualmente con:")
            print("pip install -r requirements_dashboard.txt")
            return False
    else:
        print("✅ Tutte le dipendenze sono presenti")
    
    # Avvia dashboard
    print("\n🎯 Avvio Dashboard Controller...")
    
    try:
        from bait_dashboard_app import BAITDashboardApp
        
        # Inizializza dashboard
        dashboard = BAITDashboardApp()
        
        print("\n" + "="*60)
        print("🎉 BAIT SERVICE DASHBOARD ATTIVA!")
        print("="*60)
        print("🌐 URL: http://localhost:8050")
        print("📊 Excel-like Grid per controllo quotidiano")
        print("📈 Analytics real-time e KPI")
        print("📄 Export Excel/PDF professionale")
        print("🔄 Auto-refresh ogni 30 secondi")
        print("📱 Mobile-responsive interface")
        print()
        print("🛑 Premi CTRL+C per fermare il server")
        print("="*60)
        
        # Avvia server
        dashboard.run_server(debug=False, port=8050)
        
    except KeyboardInterrupt:
        print("\n👋 Dashboard fermata dall'utente")
        return True
        
    except Exception as e:
        print(f"❌ Errore avvio dashboard: {e}")
        print("\n🔧 Troubleshooting:")
        print("1. Verifica che la porta 8050 sia libera")
        print("2. Controlla i log di errore sopra")
        print("3. Riprova con: python bait_dashboard_app.py")
        return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)