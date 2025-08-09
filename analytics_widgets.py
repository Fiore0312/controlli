"""
BAIT DASHBOARD - Advanced Analytics & Drill-Down Components
===========================================================

Sistema avanzato di analytics widgets per drill-down profondo su ogni alert
con modal popups, correlation engine e context switching intelligente.

Features implementate:
- Modal drill-down dettagliato per ogni alert con correction steps
- Correlation matrix per pattern analysis anomalie ricorrenti
- Timeline reconstruction attività tecnico giornaliera  
- Cross-reference alert simili stesso tecnico/cliente
- Context switching tra views diverse (tecnico/cliente/timeline)
- Behavioral profiling per identificazione pattern anomali

Autore: BAIT Service Dashboard Controller Agent
Data: 2025-08-09
Versione: 1.0.0 Enterprise-Grade
"""

import dash
from dash import dcc, html, Input, Output, State, callback
import pandas as pd
import plotly.express as px
import plotly.graph_objects as go
from plotly.subplots import make_subplots
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Any, Optional, Tuple
import json
import logging

logger = logging.getLogger(__name__)

class AnalyticsEngine:
    """Engine principale per analytics avanzate e drill-down."""
    
    def __init__(self):
        self.current_data = {}
        self.correlation_cache = {}
        self.pattern_cache = {}
    
    def load_alert_details(self, alert_id: str, data: Dict[str, Any]) -> Optional[Dict]:
        """
        Carica dettagli completi di un alert specifico.
        
        Args:
            alert_id: ID dell'alert da analizzare
            data: Dati dashboard completi
            
        Returns:
            Dizionario con tutti i dettagli dell'alert
        """
        try:
            if not data or 'alerts' not in data or 'active' not in data['alerts']:
                return None
            
            for alert in data['alerts']['active']:
                if alert.get('id') == alert_id:
                    # Arricchisci con dati analitici
                    alert_details = alert.copy()
                    alert_details['analytics'] = self._generate_alert_analytics(alert, data)
                    return alert_details
            
            return None
            
        except Exception as e:
            logger.error(f"Errore caricamento dettagli alert {alert_id}: {e}")
            return None
    
    def _generate_alert_analytics(self, alert: Dict, data: Dict[str, Any]) -> Dict:
        """
        Genera analytics avanzate per un alert specifico.
        
        Args:
            alert: Dati alert principale
            data: Tutti i dati dashboard
            
        Returns:
            Dizionario con analytics generate
        """
        try:
            analytics = {}
            
            tecnico = alert.get('tecnico', '')
            category = alert.get('category', '')
            
            # 1. Pattern analysis stesso tecnico
            tecnico_alerts = [
                a for a in data['alerts']['active'] 
                if a.get('tecnico') == tecnico and a.get('id') != alert.get('id')
            ]
            
            analytics['same_tecnico_alerts'] = len(tecnico_alerts)
            analytics['tecnico_patterns'] = self._analyze_tecnico_patterns(tecnico, data)
            
            # 2. Category analysis
            category_alerts = [
                a for a in data['alerts']['active']
                if a.get('category') == category and a.get('id') != alert.get('id')
            ]
            
            analytics['same_category_alerts'] = len(category_alerts)
            analytics['category_trend'] = self._analyze_category_trend(category, data)
            
            # 3. Time analysis
            analytics['time_analysis'] = self._analyze_time_patterns(alert, data)
            
            # 4. Business impact assessment
            analytics['impact_assessment'] = self._assess_business_impact(alert, data)
            
            return analytics
            
        except Exception as e:
            logger.error(f"Errore generazione analytics: {e}")
            return {}
    
    def _analyze_tecnico_patterns(self, tecnico: str, data: Dict[str, Any]) -> Dict:
        """Analizza pattern comportamentali per tecnico specifico."""
        try:
            metrics = data.get('metrics', {}).get('alerts_by_tecnico', {})
            total_alerts = metrics.get(tecnico, 0)
            
            # Calcola percentuale alert del tecnico sul totale
            total_system = sum(metrics.values()) if metrics else 1
            tecnico_percentage = (total_alerts / total_system) * 100 if total_system > 0 else 0
            
            # Analisi severity distribution
            tecnico_alerts = [
                a for a in data.get('alerts', {}).get('active', [])
                if a.get('tecnico') == tecnico
            ]
            
            priority_dist = {}
            for alert in tecnico_alerts:
                priority = alert.get('priority', 'UNKNOWN')
                priority_dist[priority] = priority_dist.get(priority, 0) + 1
            
            return {
                'total_alerts': total_alerts,
                'percentage_of_total': round(tecnico_percentage, 1),
                'priority_distribution': priority_dist,
                'risk_level': 'HIGH' if total_alerts >= 5 else 'MEDIUM' if total_alerts >= 3 else 'LOW'
            }
            
        except Exception as e:
            logger.error(f"Errore analisi pattern tecnico {tecnico}: {e}")
            return {}
    
    def _analyze_category_trend(self, category: str, data: Dict[str, Any]) -> Dict:
        """Analizza trend per categoria specifica."""
        try:
            category_alerts = [
                a for a in data.get('alerts', {}).get('active', [])
                if a.get('category') == category
            ]
            
            # Distribuzione per tecnico nella categoria
            tecnico_dist = {}
            confidence_scores = []
            
            for alert in category_alerts:
                tecnico = alert.get('tecnico', 'Unknown')
                tecnico_dist[tecnico] = tecnico_dist.get(tecnico, 0) + 1
                
                if alert.get('confidence_score'):
                    confidence_scores.append(alert['confidence_score'])
            
            avg_confidence = np.mean(confidence_scores) if confidence_scores else 0
            
            return {
                'total_in_category': len(category_alerts),
                'tecnico_distribution': tecnico_dist,
                'avg_confidence_score': round(avg_confidence, 1),
                'category_criticality': 'HIGH' if len(category_alerts) >= 8 else 'MEDIUM' if len(category_alerts) >= 3 else 'LOW'
            }
            
        except Exception as e:
            logger.error(f"Errore analisi trend categoria {category}: {e}")
            return {}
    
    def _analyze_time_patterns(self, alert: Dict, data: Dict[str, Any]) -> Dict:
        """Analizza pattern temporali."""
        try:
            created_at = alert.get('created_at')
            if not created_at:
                return {}
            
            # Parse timestamp
            alert_time = datetime.fromisoformat(created_at.replace('Z', '+00:00'))
            hour = alert_time.hour
            
            # Analizza distribuzione oraria degli alert
            all_alerts = data.get('alerts', {}).get('active', [])
            hourly_distribution = {}
            
            for a in all_alerts:
                if a.get('created_at'):
                    try:
                        a_time = datetime.fromisoformat(a['created_at'].replace('Z', '+00:00'))
                        a_hour = a_time.hour
                        hourly_distribution[a_hour] = hourly_distribution.get(a_hour, 0) + 1
                    except:
                        continue
            
            return {
                'alert_hour': hour,
                'time_category': self._categorize_time(hour),
                'hourly_distribution': hourly_distribution,
                'peak_hour': max(hourly_distribution.items(), key=lambda x: x[1])[0] if hourly_distribution else None
            }
            
        except Exception as e:
            logger.error(f"Errore analisi pattern temporali: {e}")
            return {}
    
    def _categorize_time(self, hour: int) -> str:
        """Categorizza ora del giorno."""
        if 6 <= hour < 12:
            return "MATTINA"
        elif 12 <= hour < 18:
            return "POMERIGGIO"
        elif 18 <= hour < 22:
            return "SERA"
        else:
            return "NOTTE"
    
    def _assess_business_impact(self, alert: Dict, data: Dict[str, Any]) -> Dict:
        """Valuta impatto business dell'alert."""
        try:
            priority = alert.get('priority', 'NORMAL')
            confidence = alert.get('confidence_score', 0)
            loss = alert.get('estimated_loss', 0) or 0
            
            # Calcola impact score
            priority_weights = {'IMMEDIATE': 1.0, 'URGENT': 0.7, 'NORMAL': 0.4}
            priority_weight = priority_weights.get(priority, 0.4)
            
            confidence_factor = confidence / 100.0
            loss_factor = min(loss / 50.0, 1.0)  # Normalizza perdita su €50 max
            
            impact_score = (priority_weight * 0.5) + (confidence_factor * 0.3) + (loss_factor * 0.2)
            
            # Stima tempo risoluzione
            estimated_resolution_hours = {
                'IMMEDIATE': 0.5,
                'URGENT': 2.0,
                'NORMAL': 8.0
            }.get(priority, 4.0)
            
            return {
                'impact_score': round(impact_score, 2),
                'financial_impact': loss,
                'estimated_resolution_hours': estimated_resolution_hours,
                'business_criticality': 'CRITICAL' if impact_score > 0.8 else 'HIGH' if impact_score > 0.6 else 'MEDIUM'
            }
            
        except Exception as e:
            logger.error(f"Errore assessment business impact: {e}")
            return {}
    
    def generate_correlation_matrix(self, data: Dict[str, Any]) -> go.Figure:
        """
        Genera matrice correlazioni tra tecnici e categorie alert.
        
        Args:
            data: Dati dashboard completi
            
        Returns:
            Figure Plotly con heatmap correlazioni
        """
        try:
            alerts = data.get('alerts', {}).get('active', [])
            if not alerts:
                return go.Figure()
            
            # Crea matrice tecnici x categorie
            tecnici = list(set(a.get('tecnico', 'Unknown') for a in alerts))
            categorie = list(set(a.get('category', 'unknown') for a in alerts))
            
            # Costruisci matrice conteggi
            matrix = np.zeros((len(tecnici), len(categorie)))
            
            for i, tecnico in enumerate(tecnici):
                for j, categoria in enumerate(categorie):
                    count = sum(1 for a in alerts 
                              if a.get('tecnico') == tecnico and a.get('category') == categoria)
                    matrix[i, j] = count
            
            # Crea heatmap
            fig = go.Figure(data=go.Heatmap(
                z=matrix,
                x=categorie,
                y=tecnici,
                colorscale='RdYlBu_r',
                showscale=True,
                hoverongaps=False,
                text=matrix,
                texttemplate="%{text}",
                textfont={"size": 12}
            ))
            
            fig.update_layout(
                title="Matrice Correlazione Tecnici vs Categorie Alert",
                xaxis_title="Categoria Alert",
                yaxis_title="Tecnico",
                height=400,
                font=dict(size=10)
            )
            
            return fig
            
        except Exception as e:
            logger.error(f"Errore generazione correlation matrix: {e}")
            return go.Figure()
    
    def generate_timeline_chart(self, tecnico: str, data: Dict[str, Any]) -> go.Figure:
        """
        Genera timeline chart per ricostruzione giornata tecnico.
        
        Args:
            tecnico: Nome tecnico da analizzare
            data: Dati dashboard completi
            
        Returns:
            Figure Plotly con timeline
        """
        try:
            alerts = [
                a for a in data.get('alerts', {}).get('active', [])
                if a.get('tecnico') == tecnico
            ]
            
            if not alerts:
                return go.Figure()
            
            # Prepara dati timeline
            times = []
            subjects = []
            priorities = []
            colors = []
            
            color_map = {'IMMEDIATE': 'red', 'URGENT': 'orange', 'NORMAL': 'green'}
            
            for alert in alerts:
                if alert.get('created_at'):
                    try:
                        time = datetime.fromisoformat(alert['created_at'].replace('Z', '+00:00'))
                        times.append(time)
                        subjects.append(alert.get('subject', '')[:50] + '...')
                        priority = alert.get('priority', 'NORMAL')
                        priorities.append(priority)
                        colors.append(color_map.get(priority, 'blue'))
                    except:
                        continue
            
            if not times:
                return go.Figure()
            
            # Crea scatter plot timeline
            fig = go.Figure()
            
            for i, (time, subject, priority, color) in enumerate(zip(times, subjects, priorities, colors)):
                fig.add_trace(go.Scatter(
                    x=[time],
                    y=[i],
                    mode='markers+text',
                    marker=dict(size=15, color=color),
                    text=[priority],
                    textposition="middle right",
                    hovertemplate=f"<b>{subject}</b><br>Ora: {time.strftime('%H:%M')}<br>Priorità: {priority}<extra></extra>",
                    showlegend=False,
                    name=f"Alert {i+1}"
                ))
            
            fig.update_layout(
                title=f"Timeline Alert - {tecnico}",
                xaxis_title="Ora",
                yaxis_title="Sequenza Alert",
                yaxis=dict(showticklabels=False),
                height=300,
                hovermode='closest'
            )
            
            return fig
            
        except Exception as e:
            logger.error(f"Errore generazione timeline {tecnico}: {e}")
            return go.Figure()

def create_drill_down_modal(alert_id: str, analytics_engine: AnalyticsEngine, data: Dict[str, Any]) -> html.Div:
    """
    Crea modal drill-down per alert specifico.
    
    Args:
        alert_id: ID dell'alert da analizzare
        analytics_engine: Engine analytics per dati avanzati
        data: Dati dashboard completi
        
    Returns:
        Modal HTML con dettagli completi
    """
    try:
        # Carica dettagli alert
        alert_details = analytics_engine.load_alert_details(alert_id, data)
        
        if not alert_details:
            return html.Div("Alert non trovato", className="alert alert-warning")
        
        analytics = alert_details.get('analytics', {})
        
        # Header modal
        priority = alert_details.get('priority', 'NORMAL')
        priority_colors = {'IMMEDIATE': 'danger', 'URGENT': 'warning', 'NORMAL': 'success'}
        priority_color = priority_colors.get(priority, 'secondary')
        
        modal_content = html.Div([
            # Header
            html.Div([
                html.H4([
                    html.I(className="fas fa-search-plus me-2"),
                    f"Analisi Dettagliata - {alert_id}"
                ], className="modal-title"),
                html.Span(
                    priority,
                    className=f"badge bg-{priority_color} fs-6"
                )
            ], className="modal-header d-flex justify-content-between align-items-center"),
            
            # Body
            html.Div([
                # Alert principale
                html.Div([
                    html.H5("📋 Dettagli Alert", className="text-primary"),
                    html.Ul([
                        html.Li(f"Tecnico: {alert_details.get('tecnico', 'N/A')}"),
                        html.Li(f"Categoria: {alert_details.get('category', 'N/A')}"),
                        html.Li(f"Confidence: {alert_details.get('confidence_score', 0)}%"),
                        html.Li(f"Perdita stimata: €{alert_details.get('estimated_loss', 0) or 0:.2f}"),
                        html.Li(f"Impatto business: {alert_details.get('business_impact', 'N/A')}")
                    ])
                ], className="mb-4"),
                
                # Correction steps
                html.Div([
                    html.H5("🔧 Azioni Correttive", className="text-success"),
                    html.Ol([
                        html.Li(step) for step in alert_details.get('correction_steps', [])
                    ])
                ], className="mb-4"),
                
                # Analytics avanzate
                html.Div([
                    html.H5("📊 Pattern Analysis", className="text-info"),
                    
                    # Pattern tecnico
                    html.Div([
                        html.H6("👤 Analisi Tecnico"),
                        html.Ul([
                            html.Li(f"Alert totali tecnico: {analytics.get('same_tecnico_alerts', 0) + 1}"),
                            html.Li(f"% del totale sistema: {analytics.get('tecnico_patterns', {}).get('percentage_of_total', 0)}%"),
                            html.Li(f"Livello rischio: {analytics.get('tecnico_patterns', {}).get('risk_level', 'UNKNOWN')}")
                        ])
                    ], className="mb-3"),
                    
                    # Pattern categoria
                    html.Div([
                        html.H6("📂 Analisi Categoria"),
                        html.Ul([
                            html.Li(f"Alert totali categoria: {analytics.get('same_category_alerts', 0) + 1}"),
                            html.Li(f"Confidence media: {analytics.get('category_trend', {}).get('avg_confidence_score', 0)}%"),
                            html.Li(f"Criticità categoria: {analytics.get('category_trend', {}).get('category_criticality', 'UNKNOWN')}")
                        ])
                    ], className="mb-3"),
                    
                    # Business impact
                    html.Div([
                        html.H6("💼 Business Impact"),
                        impact = analytics.get('impact_assessment', {}),
                        html.Ul([
                            html.Li(f"Impact Score: {impact.get('impact_score', 0)}"),
                            html.Li(f"Tempo risoluzione stimato: {impact.get('estimated_resolution_hours', 0)} ore"),
                            html.Li(f"Criticità business: {impact.get('business_criticality', 'UNKNOWN')}")
                        ])
                    ])
                ], className="mb-4"),
                
                # Correlation matrix
                html.Div([
                    html.H5("🔗 Correlation Analysis", className="text-warning"),
                    dcc.Graph(
                        figure=analytics_engine.generate_correlation_matrix(data),
                        style={'height': '400px'}
                    )
                ], className="mb-4"),
                
                # Timeline chart
                html.Div([
                    html.H5("⏱️ Timeline Tecnico", className="text-secondary"),
                    dcc.Graph(
                        figure=analytics_engine.generate_timeline_chart(
                            alert_details.get('tecnico', ''), data
                        ),
                        style={'height': '300px'}
                    )
                ])
                
            ], className="modal-body")
            
        ], className="modal-content")
        
        return modal_content
        
    except Exception as e:
        logger.error(f"Errore creazione drill-down modal: {e}")
        return html.Div(f"Errore caricamento dettagli: {str(e)}", className="alert alert-danger")

def create_context_switcher() -> html.Div:
    """
    Crea componente per switching tra views diverse.
    
    Returns:
        Controlli per context switching
    """
    return html.Div([
        html.H6("🔄 Context View", className="mb-2"),
        dcc.Tabs(
            id="context-tabs",
            value="tecnico-view",
            children=[
                dcc.Tab(label="👤 Per Tecnico", value="tecnico-view"),
                dcc.Tab(label="📂 Per Categoria", value="categoria-view"),
                dcc.Tab(label="⏱️ Timeline", value="timeline-view"),
                dcc.Tab(label="🔗 Correlazioni", value="correlation-view")
            ],
            style={'marginBottom': '1rem'}
        ),
        html.Div(id="context-content")
    ], className="mb-4")

# Funzioni helper per analytics
def calculate_alert_velocity(data: Dict[str, Any]) -> Dict[str, float]:
    """Calcola velocità generazione alert per tecnico."""
    try:
        metrics = data.get('metrics', {}).get('alerts_by_tecnico', {})
        
        # Simula calcolo velocity (alert per ora)
        velocity = {}
        for tecnico, count in metrics.items():
            # Assumendo giornata lavorativa 8 ore
            velocity[tecnico] = round(count / 8.0, 2)
        
        return velocity
        
    except Exception as e:
        logger.error(f"Errore calcolo alert velocity: {e}")
        return {}

def identify_risk_patterns(data: Dict[str, Any]) -> List[Dict]:
    """Identifica pattern di rischio ricorrenti."""
    try:
        alerts = data.get('alerts', {}).get('active', [])
        risk_patterns = []
        
        # Pattern 1: Tecnico con troppi alert IMMEDIATE
        tecnico_immediate = {}
        for alert in alerts:
            if alert.get('priority') == 'IMMEDIATE':
                tecnico = alert.get('tecnico', 'Unknown')
                tecnico_immediate[tecnico] = tecnico_immediate.get(tecnico, 0) + 1
        
        for tecnico, count in tecnico_immediate.items():
            if count >= 3:
                risk_patterns.append({
                    'type': 'HIGH_IMMEDIATE_ALERTS',
                    'tecnico': tecnico,
                    'count': count,
                    'severity': 'CRITICAL'
                })
        
        # Pattern 2: Categoria con alta concentrazione
        category_counts = {}
        for alert in alerts:
            cat = alert.get('category', 'unknown')
            category_counts[cat] = category_counts.get(cat, 0) + 1
        
        for category, count in category_counts.items():
            if count >= 5:
                risk_patterns.append({
                    'type': 'CATEGORY_CONCENTRATION',
                    'category': category,
                    'count': count,
                    'severity': 'HIGH'
                })
        
        return risk_patterns
        
    except Exception as e:
        logger.error(f"Errore identificazione risk patterns: {e}")
        return []

# Export principale
analytics_engine = AnalyticsEngine()