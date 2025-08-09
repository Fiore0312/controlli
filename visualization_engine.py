"""
BAIT DASHBOARD - Advanced Plotly Visualizations Engine
======================================================

Sistema visualizzazioni avanzate per business intelligence con:

- Bar chart interattivo distribuzione alert per tecnico
- Pie chart breakdown categorie con drill-down
- Scatter plot confidence vs estimated_loss per prioritizzazione
- Timeline animated per pattern analysis temporale
- Heatmap correlation matrix anomalie ricorrenti
- Interactive hover tooltips con business context
- Animation controls per trend analysis
- Custom color schemes BAIT Service branding

Autore: BAIT Service Dashboard Controller Agent
Data: 2025-08-09
Versione: 1.0.0 Enterprise-Grade
"""

import dash
from dash import dcc, html
import pandas as pd
import plotly.express as px
import plotly.graph_objects as go
from plotly.subplots import make_subplots
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Any, Optional, Tuple
import logging

logger = logging.getLogger(__name__)

class VisualizationEngine:
    """Engine principale per visualizzazioni avanzate Plotly."""
    
    def __init__(self):
        # BAIT Service color palette
        self.colors = {
            'primary': '#0d6efd',
            'success': '#198754', 
            'warning': '#fd7e14',
            'danger': '#dc3545',
            'info': '#20c997',
            'secondary': '#6c757d',
            'light': '#f8f9fa',
            'dark': '#212529'
        }
        
        self.priority_colors = {
            'IMMEDIATE': self.colors['danger'],
            'URGENT': self.colors['warning'], 
            'NORMAL': self.colors['success']
        }
        
        self.category_colors = {
            'temporal_overlap': '#e74c3c',
            'insufficient_travel_time': '#f39c12',
            'missing_timesheet': '#9b59b6',
            'schedule_discrepancy': '#3498db',
            'vehicle_inconsistency': '#1abc9c',
            'geo_inconsistency': '#34495e'
        }
    
    def create_technician_alert_distribution(self, data: Dict[str, Any], interactive: bool = True) -> go.Figure:
        """
        Crea bar chart distribuzione alert per tecnico con drill-down.
        
        Args:
            data: Dati dashboard completi
            interactive: Abilita interattività
            
        Returns:
            Figure Plotly con bar chart avanzato
        """
        try:
            metrics = data.get('metrics', {})
            tecnico_alerts = metrics.get('alerts_by_tecnico', {})
            alerts = data.get('alerts', {}).get('active', [])
            
            if not tecnico_alerts:
                return self._create_empty_figure("Nessun dato tecnico disponibile")
            
            # Prepara dati per visualizzazione
            tecnici = list(tecnico_alerts.keys())
            alert_counts = list(tecnico_alerts.values())
            
            # Calcola breakdown per priorità
            priority_breakdown = {}
            for tecnico in tecnici:
                tecnico_alert_list = [a for a in alerts if a.get('tecnico') == tecnico]
                
                breakdown = {'IMMEDIATE': 0, 'URGENT': 0, 'NORMAL': 0}
                for alert in tecnico_alert_list:
                    priority = alert.get('priority', 'NORMAL')
                    if priority in breakdown:
                        breakdown[priority] += 1
                
                priority_breakdown[tecnico] = breakdown
            
            # Crea figure con subplots
            fig = go.Figure()
            
            # Stacked bar chart per priorità
            for priority in ['IMMEDIATE', 'URGENT', 'NORMAL']:
                values = [priority_breakdown[tecnico][priority] for tecnico in tecnici]
                
                fig.add_trace(go.Bar(
                    name=priority,
                    x=tecnici,
                    y=values,
                    marker_color=self.priority_colors[priority],
                    hovertemplate=f"<b>%{{x}}</b><br>{priority}: %{{y}}<br><extra></extra>",
                    text=values,
                    textposition='inside',
                    textfont=dict(color='white', size=10)
                ))
            
            # Layout customization
            fig.update_layout(
                title={
                    'text': "📊 Distribuzione Alert per Tecnico",
                    'x': 0.5,
                    'xanchor': 'center',
                    'font': {'size': 16, 'color': self.colors['primary']}
                },
                xaxis_title="👤 Tecnico",
                yaxis_title="📈 Numero Alert",
                barmode='stack',
                
                # Professional styling
                plot_bgcolor='white',
                paper_bgcolor='white',
                font=dict(family="Segoe UI, sans-serif", size=12),
                
                # Legend
                legend=dict(
                    orientation="h",
                    yanchor="bottom",
                    y=1.02,
                    xanchor="right",
                    x=1,
                    bgcolor="rgba(255,255,255,0.8)",
                    bordercolor="rgba(0,0,0,0.2)",
                    borderwidth=1
                ),
                
                # Interactivity
                hovermode='x unified' if interactive else False,
                
                # Responsive
                autosize=True,
                height=400
            )
            
            # Grid styling
            fig.update_xaxes(
                gridcolor='rgba(0,0,0,0.1)',
                tickangle=45 if len(tecnici) > 5 else 0
            )
            fig.update_yaxes(
                gridcolor='rgba(0,0,0,0.1)',
                zeroline=True,
                zerolinecolor='rgba(0,0,0,0.2)'
            )
            
            # Add annotations per totals
            for i, (tecnico, total) in enumerate(zip(tecnici, alert_counts)):
                fig.add_annotation(
                    x=i,
                    y=total + 0.1,
                    text=f"<b>{total}</b>",
                    showarrow=False,
                    font=dict(size=12, color=self.colors['dark'])
                )
            
            return fig
            
        except Exception as e:
            logger.error(f"Errore creazione bar chart tecnici: {e}")
            return self._create_empty_figure("Errore caricamento grafico")
    
    def create_category_breakdown_pie(self, data: Dict[str, Any], donut: bool = True) -> go.Figure:
        """
        Crea pie chart breakdown categorie con drill-down.
        
        Args:
            data: Dati dashboard completi
            donut: True per donut chart, False per pie normale
            
        Returns:
            Figure Plotly con pie/donut chart
        """
        try:
            alerts = data.get('alerts', {}).get('active', [])
            
            if not alerts:
                return self._create_empty_figure("Nessun alert disponibile")
            
            # Conta categorie
            category_counts = {}
            for alert in alerts:
                category = alert.get('category', 'unknown')
                category_counts[category] = category_counts.get(category, 0) + 1
            
            if not category_counts:
                return self._create_empty_figure("Nessuna categoria disponibile")
            
            # Prepara dati per pie chart
            categories = list(category_counts.keys())
            counts = list(category_counts.values())
            
            # Labels user-friendly
            category_labels = {
                'temporal_overlap': '⏱️ Sovrapposizioni Temporali',
                'insufficient_travel_time': '🚗 Tempi Viaggio Insufficienti',
                'missing_timesheet': '📋 Rapportini Mancanti',
                'schedule_discrepancy': '📅 Discrepanze Calendario',
                'vehicle_inconsistency': '🚙 Inconsistenze Veicoli',
                'geo_inconsistency': '📍 Inconsistenze Geografiche'
            }
            
            labels = [category_labels.get(cat, cat.replace('_', ' ').title()) for cat in categories]
            
            # Colors dinamici
            colors = [self.category_colors.get(cat, self.colors['secondary']) for cat in categories]
            
            # Crea pie/donut chart
            fig = go.Figure(data=[go.Pie(
                labels=labels,
                values=counts,
                hole=0.4 if donut else 0,
                
                # Styling
                marker=dict(
                    colors=colors,
                    line=dict(color='white', width=2)
                ),
                
                # Text info
                textinfo='label+percent+value',
                textposition='outside',
                textfont=dict(size=11),
                
                # Hover template
                hovertemplate="<b>%{label}</b><br>" +
                             "Alert: %{value}<br>" +
                             "Percentuale: %{percent}<br>" +
                             "<extra></extra>",
                
                # Pull effect per highlight
                pull=[0.1 if count == max(counts) else 0 for count in counts]
            )])
            
            # Layout
            fig.update_layout(
                title={
                    'text': "📂 Breakdown Categorie Alert",
                    'x': 0.5,
                    'xanchor': 'center',
                    'font': {'size': 16, 'color': self.colors['primary']}
                },
                
                # Center annotation per donut
                annotations=[dict(
                    text=f"<b>{sum(counts)}</b><br>Alert Totali",
                    x=0.5, y=0.5,
                    font_size=14,
                    showarrow=False,
                    font_color=self.colors['dark']
                )] if donut else [],
                
                # Professional styling
                plot_bgcolor='white',
                paper_bgcolor='white',
                font=dict(family="Segoe UI, sans-serif", size=12),
                
                # Legend
                showlegend=True,
                legend=dict(
                    orientation="v",
                    yanchor="middle",
                    y=0.5,
                    xanchor="left",
                    x=1.05,
                    bgcolor="rgba(255,255,255,0.8)",
                    bordercolor="rgba(0,0,0,0.2)",
                    borderwidth=1
                ),
                
                # Responsive
                autosize=True,
                height=400
            )
            
            return fig
            
        except Exception as e:
            logger.error(f"Errore creazione pie chart categorie: {e}")
            return self._create_empty_figure("Errore caricamento grafico")
    
    def create_confidence_loss_scatter(self, data: Dict[str, Any], bubble: bool = True) -> go.Figure:
        """
        Crea scatter plot confidence vs estimated_loss per prioritizzazione.
        
        Args:
            data: Dati dashboard completi
            bubble: True per bubble chart con size variabile
            
        Returns:
            Figure Plotly con scatter plot avanzato
        """
        try:
            alerts = data.get('alerts', {}).get('active', [])
            
            if not alerts:
                return self._create_empty_figure("Nessun alert disponibile")
            
            # Prepara dati per scatter
            confidence_scores = []
            estimated_losses = []
            priorities = []
            tecnici = []
            subjects = []
            alert_ids = []
            
            for alert in alerts:
                confidence = alert.get('confidence_score', 0)
                loss = alert.get('estimated_loss') or 0
                priority = alert.get('priority', 'NORMAL')
                tecnico = alert.get('tecnico', 'Unknown')
                subject = alert.get('subject', '')[:50] + '...'
                alert_id = alert.get('id', '')
                
                confidence_scores.append(confidence)
                estimated_losses.append(loss)
                priorities.append(priority)
                tecnici.append(tecnico)
                subjects.append(subject)
                alert_ids.append(alert_id)
            
            if not confidence_scores:
                return self._create_empty_figure("Nessun dato per scatter plot")
            
            # Crea figure
            fig = go.Figure()
            
            # Scatter per ogni priorità
            for priority in ['IMMEDIATE', 'URGENT', 'NORMAL']:
                mask = [p == priority for p in priorities]
                
                if not any(mask):
                    continue
                
                x_vals = [conf for i, conf in enumerate(confidence_scores) if mask[i]]
                y_vals = [loss for i, loss in enumerate(estimated_losses) if mask[i]]
                tecnici_vals = [tec for i, tec in enumerate(tecnici) if mask[i]]
                subjects_vals = [subj for i, subj in enumerate(subjects) if mask[i]]
                ids_vals = [aid for i, aid in enumerate(alert_ids) if mask[i]]
                
                # Size per bubble chart
                sizes = [max(10, loss/2) for loss in y_vals] if bubble else None
                
                fig.add_trace(go.Scatter(
                    x=x_vals,
                    y=y_vals,
                    mode='markers',
                    name=priority,
                    marker=dict(
                        color=self.priority_colors[priority],
                        size=sizes if bubble else 8,
                        opacity=0.7,
                        line=dict(width=1, color='white')
                    ),
                    
                    # Custom hover
                    customdata=list(zip(tecnici_vals, subjects_vals, ids_vals)),
                    hovertemplate="<b>%{customdata[2]}</b><br>" +
                                 "👤 Tecnico: %{customdata[0]}<br>" +
                                 "📋 %{customdata[1]}<br>" +
                                 "🎯 Confidence: %{x}%<br>" +
                                 "💰 Perdita: €%{y:.2f}<br>" +
                                 f"⚡ Priorità: {priority}<br>" +
                                 "<extra></extra>"
                ))
            
            # Layout
            fig.update_layout(
                title={
                    'text': "🎯 Confidence vs Perdita Stimata",
                    'x': 0.5,
                    'xanchor': 'center',
                    'font': {'size': 16, 'color': self.colors['primary']}
                },
                
                xaxis_title="🎯 Confidence Score (%)",
                yaxis_title="💰 Perdita Stimata (€)",
                
                # Professional styling
                plot_bgcolor='white',
                paper_bgcolor='white',
                font=dict(family="Segoe UI, sans-serif", size=12),
                
                # Legend
                legend=dict(
                    title="Priorità Alert",
                    orientation="v",
                    yanchor="top",
                    y=1,
                    xanchor="left",
                    x=1.02,
                    bgcolor="rgba(255,255,255,0.8)",
                    bordercolor="rgba(0,0,0,0.2)",
                    borderwidth=1
                ),
                
                # Responsive
                autosize=True,
                height=450,
                hovermode='closest'
            )
            
            # Grid e assi
            fig.update_xaxes(
                gridcolor='rgba(0,0,0,0.1)',
                range=[0, 105],
                ticksuffix='%'
            )
            fig.update_yaxes(
                gridcolor='rgba(0,0,0,0.1)',
                tickprefix='€'
            )
            
            # Add quadrant lines per interpretazione
            max_loss = max(estimated_losses) if estimated_losses else 0
            
            # Vertical line at 80% confidence
            fig.add_vline(
                x=80, 
                line_dash="dash", 
                line_color="gray",
                annotation_text="High Confidence",
                annotation_position="top"
            )
            
            # Horizontal line at average loss
            avg_loss = np.mean([l for l in estimated_losses if l > 0]) if any(l > 0 for l in estimated_losses) else 0
            if avg_loss > 0:
                fig.add_hline(
                    y=avg_loss,
                    line_dash="dash",
                    line_color="gray", 
                    annotation_text=f"Avg Loss: €{avg_loss:.2f}",
                    annotation_position="right"
                )
            
            return fig
            
        except Exception as e:
            logger.error(f"Errore creazione scatter plot confidence/loss: {e}")
            return self._create_empty_figure("Errore caricamento grafico")
    
    def create_timeline_animation(self, data: Dict[str, Any], tecnico: Optional[str] = None) -> go.Figure:
        """
        Crea timeline animata per pattern analysis temporale.
        
        Args:
            data: Dati dashboard completi
            tecnico: Filtra per tecnico specifico (optional)
            
        Returns:
            Figure Plotly con timeline animata
        """
        try:
            alerts = data.get('alerts', {}).get('active', [])
            
            if tecnico:
                alerts = [a for a in alerts if a.get('tecnico') == tecnico]
            
            if not alerts:
                return self._create_empty_figure("Nessun alert per timeline")
            
            # Prepara dati temporali
            timeline_data = []
            
            for alert in alerts:
                created_at = alert.get('created_at')
                if not created_at:
                    continue
                
                try:
                    timestamp = datetime.fromisoformat(created_at.replace('Z', '+00:00'))
                    
                    timeline_data.append({
                        'timestamp': timestamp,
                        'hour': timestamp.hour,
                        'minute': timestamp.minute,
                        'tecnico': alert.get('tecnico', 'Unknown'),
                        'priority': alert.get('priority', 'NORMAL'),
                        'category': alert.get('category', 'unknown'),
                        'subject': alert.get('subject', '')[:40] + '...',
                        'confidence': alert.get('confidence_score', 0),
                        'loss': alert.get('estimated_loss') or 0,
                        'alert_id': alert.get('id', '')
                    })
                except:
                    continue
            
            if not timeline_data:
                return self._create_empty_figure("Nessun dato temporale valido")
            
            # Ordina per timestamp
            timeline_data.sort(key=lambda x: x['timestamp'])
            
            # Crea figure animata
            fig = go.Figure()
            
            # Timeline scatter
            for i, data_point in enumerate(timeline_data):
                fig.add_trace(go.Scatter(
                    x=[data_point['hour'] + data_point['minute']/60],
                    y=[i],
                    mode='markers+text',
                    marker=dict(
                        color=self.priority_colors[data_point['priority']],
                        size=max(8, data_point['confidence']/10),
                        symbol='circle',
                        line=dict(width=1, color='white')
                    ),
                    text=data_point['priority'][0],  # First letter
                    textposition='middle center',
                    textfont=dict(color='white', size=8),
                    
                    customdata=[data_point['tecnico'], data_point['subject'], 
                              data_point['alert_id'], data_point['category']],
                    hovertemplate="<b>%{customdata[2]}</b><br>" +
                                 "👤 %{customdata[0]}<br>" +
                                 "📋 %{customdata[1]}<br>" +
                                 "📂 %{customdata[3]}<br>" +
                                 "🕒 Ora: %{x:.1f}<br>" +
                                 "🎯 Confidence: " + str(data_point['confidence']) + "%<br>" +
                                 "<extra></extra>",
                    
                    showlegend=False,
                    name=f"Alert {i+1}"
                ))
            
            # Layout timeline
            fig.update_layout(
                title={
                    'text': f"⏱️ Timeline Alert {f'- {tecnico}' if tecnico else ''}",
                    'x': 0.5,
                    'xanchor': 'center',
                    'font': {'size': 16, 'color': self.colors['primary']}
                },
                
                xaxis_title="🕒 Ora del Giorno",
                yaxis_title="📈 Sequenza Alert",
                
                # Timeline styling
                plot_bgcolor='white',
                paper_bgcolor='white',
                font=dict(family="Segoe UI, sans-serif", size=12),
                
                # Y-axis
                yaxis=dict(
                    showticklabels=False,
                    gridcolor='rgba(0,0,0,0.1)'
                ),
                
                # X-axis (hours)
                xaxis=dict(
                    range=[0, 24],
                    tickmode='array',
                    tickvals=list(range(0, 25, 2)),
                    ticktext=[f"{h}:00" for h in range(0, 25, 2)],
                    gridcolor='rgba(0,0,0,0.1)'
                ),
                
                # Responsive
                autosize=True,
                height=400,
                hovermode='closest'
            )
            
            # Add time zone markers
            work_hours = [9, 18]  # 9-18 work hours
            for hour in work_hours:
                fig.add_vline(
                    x=hour,
                    line_dash="dot",
                    line_color=self.colors['info'],
                    opacity=0.5,
                    annotation_text=f"{'Inizio' if hour == 9 else 'Fine'} Lavoro",
                    annotation_position="top" if hour == 9 else "bottom"
                )
            
            return fig
            
        except Exception as e:
            logger.error(f"Errore creazione timeline animata: {e}")
            return self._create_empty_figure("Errore caricamento timeline")
    
    def create_correlation_heatmap(self, data: Dict[str, Any]) -> go.Figure:
        """
        Crea heatmap correlation matrix anomalie ricorrenti.
        
        Args:
            data: Dati dashboard completi
            
        Returns:
            Figure Plotly con heatmap correlazioni
        """
        try:
            alerts = data.get('alerts', {}).get('active', [])
            
            if not alerts:
                return self._create_empty_figure("Nessun alert per correlazioni")
            
            # Estrai tecnici e categorie
            tecnici = list(set(a.get('tecnico', 'Unknown') for a in alerts))
            categories = list(set(a.get('category', 'unknown') for a in alerts))
            
            if not tecnici or not categories:
                return self._create_empty_figure("Dati insufficienti per correlazioni")
            
            # Crea matrice correlazioni
            correlation_matrix = np.zeros((len(tecnici), len(categories)))
            
            for i, tecnico in enumerate(tecnici):
                for j, category in enumerate(categories):
                    count = sum(1 for a in alerts 
                              if a.get('tecnico') == tecnico and a.get('category') == category)
                    correlation_matrix[i, j] = count
            
            # Category labels user-friendly
            category_labels = {
                'temporal_overlap': 'Sovrapposizioni\nTemporali',
                'insufficient_travel_time': 'Tempi Viaggio\nInsufficenti', 
                'missing_timesheet': 'Rapportini\nMancanti',
                'schedule_discrepancy': 'Discrepanze\nCalendario',
                'vehicle_inconsistency': 'Inconsistenze\nVeicoli',
                'geo_inconsistency': 'Inconsistenze\nGeografiche'
            }
            
            y_labels = [category_labels.get(cat, cat.replace('_', '\n').title()) for cat in categories]
            
            # Crea heatmap
            fig = go.Figure(data=go.Heatmap(
                z=correlation_matrix,
                x=tecnici,
                y=y_labels,
                
                # Color scale
                colorscale='RdYlBu_r',
                showscale=True,
                colorbar=dict(
                    title="Alert Count",
                    titleside="right",
                    tickmode="linear",
                    tick0=0,
                    dtick=1
                ),
                
                # Text annotations
                text=correlation_matrix,
                texttemplate="%{text}",
                textfont=dict(size=12, color='white'),
                
                # Hover
                hovertemplate="<b>%{x}</b><br>" +
                             "%{y}<br>" +
                             "Alert: %{z}<br>" +
                             "<extra></extra>"
            ))
            
            # Layout
            fig.update_layout(
                title={
                    'text': "🔗 Matrice Correlazione Tecnici vs Categorie",
                    'x': 0.5,
                    'xanchor': 'center',
                    'font': {'size': 16, 'color': self.colors['primary']}
                },
                
                xaxis_title="👤 Tecnico",
                yaxis_title="📂 Categoria Alert",
                
                # Professional styling
                plot_bgcolor='white',
                paper_bgcolor='white',
                font=dict(family="Segoe UI, sans-serif", size=10),
                
                # Responsive
                autosize=True,
                height=400
            )
            
            # Axis styling
            fig.update_xaxes(side='bottom', tickangle=45 if len(tecnici) > 4 else 0)
            fig.update_yaxes(side='left')
            
            return fig
            
        except Exception as e:
            logger.error(f"Errore creazione correlation heatmap: {e}")
            return self._create_empty_figure("Errore caricamento correlazioni")
    
    def create_combined_dashboard_charts(self, data: Dict[str, Any]) -> html.Div:
        """
        Crea pannello combinato con tutti i charts principali.
        
        Args:
            data: Dati dashboard completi
            
        Returns:
            Div HTML con charts combinati
        """
        try:
            return html.Div([
                # Row 1: Bar chart + Pie chart
                html.Div([
                    html.Div([
                        dcc.Graph(
                            figure=self.create_technician_alert_distribution(data),
                            config={'displayModeBar': True, 'displaylogo': False}
                        )
                    ], className="col-lg-8"),
                    
                    html.Div([
                        dcc.Graph(
                            figure=self.create_category_breakdown_pie(data),
                            config={'displayModeBar': True, 'displaylogo': False}
                        )
                    ], className="col-lg-4")
                ], className="row mb-4"),
                
                # Row 2: Scatter plot + Timeline
                html.Div([
                    html.Div([
                        dcc.Graph(
                            figure=self.create_confidence_loss_scatter(data),
                            config={'displayModeBar': True, 'displaylogo': False}
                        )
                    ], className="col-lg-6"),
                    
                    html.Div([
                        dcc.Graph(
                            figure=self.create_timeline_animation(data),
                            config={'displayModeBar': True, 'displaylogo': False}
                        )
                    ], className="col-lg-6")
                ], className="row mb-4"),
                
                # Row 3: Correlation heatmap full width
                html.Div([
                    html.Div([
                        dcc.Graph(
                            figure=self.create_correlation_heatmap(data),
                            config={'displayModeBar': True, 'displaylogo': False}
                        )
                    ], className="col-12")
                ], className="row mb-4")
                
            ])
            
        except Exception as e:
            logger.error(f"Errore creazione dashboard charts combinati: {e}")
            return html.Div("Errore caricamento visualizzazioni", className="alert alert-danger")
    
    def _create_empty_figure(self, message: str = "Nessun dato disponibile") -> go.Figure:
        """Crea figure vuota con messaggio."""
        fig = go.Figure()
        
        fig.add_annotation(
            text=message,
            xref="paper", yref="paper",
            x=0.5, y=0.5,
            xanchor='center', yanchor='middle',
            showarrow=False,
            font=dict(size=16, color=self.colors['secondary'])
        )
        
        fig.update_layout(
            plot_bgcolor='white',
            paper_bgcolor='white',
            height=300,
            xaxis={'visible': False},
            yaxis={'visible': False}
        )
        
        return fig

# Export engine globale
visualization_engine = VisualizationEngine()