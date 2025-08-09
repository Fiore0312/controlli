"""
BAIT DASHBOARD - Advanced Filtering Components
==============================================

Sistema filtri dinamici avanzato per exploration dati con:
- Multi-select dropdown tecnici con conteggio alert
- Priority checkbox con color-coding visivo
- Category filter specializzato per tipologie anomalie
- Date range picker per analisi temporale
- Confidence score slider per quality filtering
- Search globale cross-columns con highlighting
- Reset all filters functionality

Autore: BAIT Service Dashboard Controller Agent
Data: 2025-08-09
Versione: 1.0.0 Enterprise-Grade
"""

import dash
from dash import dcc, html, Input, Output, State, callback
import pandas as pd
from datetime import datetime, date, timedelta
from typing import Dict, List, Any, Optional, Tuple
import logging

logger = logging.getLogger(__name__)

class FilterEngine:
    """Engine principale per gestione filtri avanzati."""
    
    def __init__(self):
        self.active_filters = {
            'tecnici': [],
            'priorities': [],
            'categories': [],
            'confidence_range': [0, 100],
            'date_range': None,
            'search_text': ''
        }
        self.filter_history = []
    
    def update_filter(self, filter_type: str, value: Any) -> Dict:
        """
        Aggiorna filtro specifico e mantiene history.
        
        Args:
            filter_type: Tipo filtro da aggiornare
            value: Nuovo valore filtro
            
        Returns:
            Dizionario filtri aggiornati
        """
        try:
            # Salva stato precedente in history
            self.filter_history.append(self.active_filters.copy())
            
            # Mantieni solo ultimi 10 stati
            if len(self.filter_history) > 10:
                self.filter_history.pop(0)
            
            # Aggiorna filtro
            self.active_filters[filter_type] = value
            
            logger.info(f"Filtro {filter_type} aggiornato: {value}")
            return self.active_filters.copy()
            
        except Exception as e:
            logger.error(f"Errore aggiornamento filtro {filter_type}: {e}")
            return self.active_filters
    
    def reset_all_filters(self) -> Dict:
        """Reset completo di tutti i filtri."""
        try:
            self.filter_history.append(self.active_filters.copy())
            
            self.active_filters = {
                'tecnici': [],
                'priorities': [],
                'categories': [],
                'confidence_range': [0, 100],
                'date_range': None,
                'search_text': ''
            }
            
            logger.info("Tutti i filtri resettati")
            return self.active_filters.copy()
            
        except Exception as e:
            logger.error(f"Errore reset filtri: {e}")
            return self.active_filters
    
    def apply_filters_to_dataframe(self, df: pd.DataFrame, data: Dict[str, Any]) -> pd.DataFrame:
        """
        Applica tutti i filtri attivi al DataFrame.
        
        Args:
            df: DataFrame originale alert
            data: Dati dashboard completi per context
            
        Returns:
            DataFrame filtrato
        """
        try:
            if df.empty:
                return df
            
            filtered_df = df.copy()
            
            # Filtro tecnici
            if self.active_filters['tecnici']:
                if 'Tecnico' in filtered_df.columns:
                    filtered_df = filtered_df[filtered_df['Tecnico'].isin(self.active_filters['tecnici'])]
            
            # Filtro priorità
            if self.active_filters['priorities']:
                if 'Priorità' in filtered_df.columns:
                    filtered_df = filtered_df[filtered_df['Priorità'].isin(self.active_filters['priorities'])]
            
            # Filtro categorie
            if self.active_filters['categories']:
                if 'Categoria' in filtered_df.columns:
                    filtered_df = filtered_df[filtered_df['Categoria'].isin(self.active_filters['categories'])]
            
            # Filtro confidence score
            if 'Confidence (%)' in filtered_df.columns:
                min_conf, max_conf = self.active_filters['confidence_range']
                filtered_df = filtered_df[
                    (filtered_df['Confidence (%)'] >= min_conf) & 
                    (filtered_df['Confidence (%)'] <= max_conf)
                ]
            
            # Search globale
            if self.active_filters['search_text']:
                search_text = self.active_filters['search_text'].lower()
                mask = filtered_df.astype(str).apply(
                    lambda x: x.str.lower().str.contains(search_text, na=False)
                ).any(axis=1)
                filtered_df = filtered_df[mask]
            
            logger.info(f"Filtri applicati: {len(df)} → {len(filtered_df)} record")
            return filtered_df
            
        except Exception as e:
            logger.error(f"Errore applicazione filtri: {e}")
            return df
    
    def get_filter_stats(self, original_df: pd.DataFrame, filtered_df: pd.DataFrame) -> Dict:
        """Calcola statistiche impatto filtri."""
        try:
            return {
                'total_records': len(original_df),
                'filtered_records': len(filtered_df),
                'filter_efficiency': round((len(filtered_df) / len(original_df)) * 100, 1) if len(original_df) > 0 else 0,
                'records_hidden': len(original_df) - len(filtered_df)
            }
        except Exception as e:
            logger.error(f"Errore calcolo filter stats: {e}")
            return {}

def create_advanced_filters_panel(data: Dict[str, Any]) -> html.Div:
    """
    Crea pannello completo filtri avanzati.
    
    Args:
        data: Dati dashboard per opzioni filtri
        
    Returns:
        Pannello HTML con tutti i controlli filtro
    """
    try:
        # Estrai opzioni per filtri
        tecnici_options = []
        priority_options = []
        category_options = []
        
        if 'metrics' in data and 'alerts_by_tecnico' in data['metrics']:
            for tecnico, count in data['metrics']['alerts_by_tecnico'].items():
                tecnici_options.append({
                    'label': f"👤 {tecnico} ({count})",
                    'value': tecnico
                })
        
        # Priority options con icons
        priority_options = [
            {'label': '🔴 IMMEDIATE', 'value': 'IMMEDIATE'},
            {'label': '🟠 URGENT', 'value': 'URGENT'},
            {'label': '🟢 NORMAL', 'value': 'NORMAL'}
        ]
        
        # Category options
        if 'alerts' in data and 'active' in data['alerts']:
            categories = set()
            for alert in data['alerts']['active']:
                if alert.get('category'):
                    categories.add(alert['category'])
            
            category_options = [
                {'label': f"📂 {cat.replace('_', ' ').title()}", 'value': cat}
                for cat in sorted(categories)
            ]
        
        return html.Div([
            # Header filtri
            html.Div([
                html.H5([
                    html.I(className="fas fa-filter me-2"),
                    "Filtri Dinamici Avanzati"
                ], className="text-primary mb-0"),
                html.Button([
                    html.I(className="fas fa-undo me-1"),
                    "Reset All"
                ], 
                id="reset-all-filters", 
                className="btn btn-outline-secondary btn-sm",
                style={'fontSize': '0.8rem'})
            ], className="d-flex justify-content-between align-items-center mb-3"),
            
            # Row 1: Tecnici e Priorità
            html.Div([
                html.Div([
                    html.Label("👤 Filtra per Tecnico", className="form-label small fw-bold"),
                    dcc.Dropdown(
                        id="advanced-tecnico-filter",
                        options=tecnici_options,
                        placeholder="Seleziona tecnici...",
                        multi=True,
                        className="mb-2",
                        style={'fontSize': '0.9rem'}
                    )
                ], className="col-md-6"),
                
                html.Div([
                    html.Label("⚡ Filtra per Priorità", className="form-label small fw-bold"),
                    dcc.Dropdown(
                        id="advanced-priority-filter",
                        options=priority_options,
                        placeholder="Seleziona priorità...",
                        multi=True,
                        className="mb-2",
                        style={'fontSize': '0.9rem'}
                    )
                ], className="col-md-6")
            ], className="row mb-3"),
            
            # Row 2: Categorie e Confidence
            html.Div([
                html.Div([
                    html.Label("📂 Filtra per Categoria", className="form-label small fw-bold"),
                    dcc.Dropdown(
                        id="advanced-category-filter",
                        options=category_options,
                        placeholder="Seleziona categorie...",
                        multi=True,
                        className="mb-2",
                        style={'fontSize': '0.9rem'}
                    )
                ], className="col-md-6"),
                
                html.Div([
                    html.Label("🎯 Confidence Score Range", className="form-label small fw-bold"),
                    dcc.RangeSlider(
                        id="confidence-range-slider",
                        min=0,
                        max=100,
                        step=5,
                        value=[0, 100],
                        marks={
                            0: {'label': '0%', 'style': {'fontSize': '0.8rem'}},
                            25: {'label': '25%', 'style': {'fontSize': '0.8rem'}},
                            50: {'label': '50%', 'style': {'fontSize': '0.8rem'}},
                            75: {'label': '75%', 'style': {'fontSize': '0.8rem'}},
                            100: {'label': '100%', 'style': {'fontSize': '0.8rem'}}
                        },
                        tooltip={"placement": "bottom", "always_visible": True},
                        className="mb-3"
                    )
                ], className="col-md-6")
            ], className="row mb-3"),
            
            # Row 3: Date Range e Search
            html.Div([
                html.Div([
                    html.Label("📅 Periodo Analisi", className="form-label small fw-bold"),
                    dcc.DatePickerRange(
                        id="date-range-picker",
                        start_date=date.today() - timedelta(days=7),
                        end_date=date.today(),
                        display_format='DD/MM/YYYY',
                        style={'fontSize': '0.9rem'},
                        className="mb-2"
                    )
                ], className="col-md-6"),
                
                html.Div([
                    html.Label("🔍 Search Globale", className="form-label small fw-bold"),
                    dcc.Input(
                        id="global-search-input",
                        type="text",
                        placeholder="Cerca in tutti i campi...",
                        className="form-control mb-2",
                        style={'fontSize': '0.9rem'},
                        debounce=True
                    )
                ], className="col-md-6")
            ], className="row mb-3"),
            
            # Filter stats e quick actions
            html.Div([
                html.Div([
                    html.Div(id="filter-stats-display", className="small text-muted")
                ], className="col-md-8"),
                
                html.Div([
                    html.Button([
                        html.I(className="fas fa-save me-1"),
                        "Salva Filtri"
                    ], 
                    id="save-filters", 
                    className="btn btn-outline-primary btn-sm me-2",
                    style={'fontSize': '0.8rem'}),
                    
                    html.Button([
                        html.I(className="fas fa-download me-1"),
                        "Export Filtrati"
                    ], 
                    id="export-filtered", 
                    className="btn btn-outline-success btn-sm",
                    style={'fontSize': '0.8rem'})
                ], className="col-md-4 text-end")
            ], className="row"),
            
            # Quick filters preset
            html.Hr(),
            html.Div([
                html.Label("⚡ Filtri Rapidi Preset", className="form-label small fw-bold mb-2"),
                html.Div([
                    html.Button("🔴 Solo Critici", id="preset-critical", className="btn btn-outline-danger btn-sm me-2 mb-1"),
                    html.Button("👤 Top Tecnico", id="preset-top-tech", className="btn btn-outline-warning btn-sm me-2 mb-1"),
                    html.Button("📈 Alta Confidence", id="preset-high-conf", className="btn btn-outline-success btn-sm me-2 mb-1"),
                    html.Button("💰 Con Perdite", id="preset-with-loss", className="btn btn-outline-info btn-sm mb-1")
                ], style={'fontSize': '0.8rem'})
            ], className="mb-2")
            
        ], className="card border-primary mb-4", style={'padding': '1rem'})
        
    except Exception as e:
        logger.error(f"Errore creazione pannello filtri: {e}")
        return html.Div("Errore caricamento filtri", className="alert alert-warning")

def create_filter_breadcrumb(filter_engine: FilterEngine) -> html.Div:
    """
    Crea breadcrumb dei filtri attivi.
    
    Args:
        filter_engine: Engine filtri per stato attuale
        
    Returns:
        Breadcrumb HTML con filtri attivi
    """
    try:
        active_filters = filter_engine.active_filters
        breadcrumb_items = []
        
        # Aggiungi item per ogni filtro attivo
        if active_filters['tecnici']:
            breadcrumb_items.append(
                html.Span([
                    html.I(className="fas fa-user me-1"),
                    f"Tecnici: {len(active_filters['tecnici'])}",
                    html.Button("×", className="btn-close btn-close-white ms-1", 
                              style={'fontSize': '0.6rem'}, id="clear-tecnici")
                ], className="badge bg-primary me-2")
            )
        
        if active_filters['priorities']:
            breadcrumb_items.append(
                html.Span([
                    html.I(className="fas fa-exclamation me-1"),
                    f"Priorità: {len(active_filters['priorities'])}",
                    html.Button("×", className="btn-close btn-close-white ms-1", 
                              style={'fontSize': '0.6rem'}, id="clear-priorities")
                ], className="badge bg-warning me-2")
            )
        
        if active_filters['categories']:
            breadcrumb_items.append(
                html.Span([
                    html.I(className="fas fa-folder me-1"),
                    f"Categorie: {len(active_filters['categories'])}",
                    html.Button("×", className="btn-close btn-close-white ms-1", 
                              style={'fontSize': '0.6rem'}, id="clear-categories")
                ], className="badge bg-info me-2")
            )
        
        if active_filters['confidence_range'] != [0, 100]:
            min_conf, max_conf = active_filters['confidence_range']
            breadcrumb_items.append(
                html.Span([
                    html.I(className="fas fa-percentage me-1"),
                    f"Confidence: {min_conf}%-{max_conf}%",
                    html.Button("×", className="btn-close btn-close-white ms-1", 
                              style={'fontSize': '0.6rem'}, id="clear-confidence")
                ], className="badge bg-success me-2")
            )
        
        if active_filters['search_text']:
            breadcrumb_items.append(
                html.Span([
                    html.I(className="fas fa-search me-1"),
                    f"Search: '{active_filters['search_text'][:20]}...'",
                    html.Button("×", className="btn-close btn-close-white ms-1", 
                              style={'fontSize': '0.6rem'}, id="clear-search")
                ], className="badge bg-secondary me-2")
            )
        
        if not breadcrumb_items:
            return html.Div("Nessun filtro attivo", className="text-muted small")
        
        return html.Div([
            html.Small("Filtri attivi: ", className="text-muted me-2"),
            *breadcrumb_items
        ], className="mb-2")
        
    except Exception as e:
        logger.error(f"Errore creazione breadcrumb: {e}")
        return html.Div("Errore breadcrumb", className="text-muted small")

def create_filter_suggestions(data: Dict[str, Any], current_filters: Dict) -> html.Div:
    """
    Suggerisce filtri intelligenti basati sui dati.
    
    Args:
        data: Dati dashboard per analisi
        current_filters: Filtri attualmente attivi
        
    Returns:
        Pannello con suggerimenti filtri
    """
    try:
        suggestions = []
        
        # Analizza dati per suggerimenti intelligenti
        if 'metrics' in data and 'alerts_by_tecnico' in data['metrics']:
            # Suggerisci tecnico con più alert
            tecnico_top = max(
                data['metrics']['alerts_by_tecnico'].items(), 
                key=lambda x: x[1]
            )
            
            if tecnico_top[1] >= 3 and tecnico_top[0] not in current_filters.get('tecnici', []):
                suggestions.append({
                    'type': 'tecnico',
                    'value': tecnico_top[0],
                    'reason': f"Ha {tecnico_top[1]} alert attivi",
                    'action_text': f"Filtra {tecnico_top[0]}"
                })
        
        # Suggerisci filtro per alert critici se presenti
        if 'metrics' in data and data['metrics'].get('critical_alerts', 0) > 0:
            if 'IMMEDIATE' not in current_filters.get('priorities', []):
                suggestions.append({
                    'type': 'priority',
                    'value': 'IMMEDIATE',
                    'reason': f"{data['metrics']['critical_alerts']} alert critici",
                    'action_text': "Mostra solo critici"
                })
        
        # Suggerisci filtro alta confidence
        if 'alerts' in data:
            high_conf_count = sum(
                1 for alert in data['alerts'].get('active', [])
                if alert.get('confidence_score', 0) >= 95
            )
            
            if high_conf_count >= 3 and current_filters.get('confidence_range') == [0, 100]:
                suggestions.append({
                    'type': 'confidence',
                    'value': [95, 100],
                    'reason': f"{high_conf_count} alert alta confidence",
                    'action_text': "Filtra alta confidence"
                })
        
        if not suggestions:
            return html.Div()
        
        suggestion_items = []
        for i, sugg in enumerate(suggestions[:3]):  # Max 3 suggerimenti
            suggestion_items.append(
                html.Button([
                    html.I(className="fas fa-lightbulb me-1"),
                    sugg['action_text'],
                    html.Small(f" ({sugg['reason']})", className="text-muted ms-1")
                ], 
                id=f"suggestion-{i}",
                className="btn btn-outline-info btn-sm me-2 mb-1",
                **{'data-type': sugg['type'], 'data-value': str(sugg['value'])}
                )
            )
        
        return html.Div([
            html.Small("💡 Suggerimenti filtri: ", className="text-muted me-2"),
            *suggestion_items
        ], className="mb-2")
        
    except Exception as e:
        logger.error(f"Errore creazione suggerimenti: {e}")
        return html.Div()

# Export engine globale
filter_engine = FilterEngine()