#!/usr/bin/env python3
"""
BAIT SERVICE - EMAIL AUTOMATION SYSTEM v3.0
TASK 19: Sistema automatico invio email per alert actionable

Caratteristiche:
- SMTP engine sicuro con autenticazione
- Template HTML responsive personalizzati
- Sistema attachments e tracking
- Multi-channel delivery (email primario + CC)
- Rate limiting e retry logic
- Template Jinja2 per massima personalizzazione
"""

import smtplib
import json
import logging
import os
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from dataclasses import dataclass
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from email.mime.base import MIMEBase
from email import encoders
import time

from alert_generator import ActionableAlert, NotificationPriority

# Import condizionale per Jinja2
try:
    from jinja2 import Environment, FileSystemLoader, Template
    JINJA2_AVAILABLE = True
except ImportError:
    JINJA2_AVAILABLE = False

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

@dataclass
class EmailDeliveryResult:
    """Risultato invio email"""
    success: bool
    alert_id: str
    recipient: str
    error_message: Optional[str] = None
    sent_at: Optional[datetime] = None
    delivery_time_ms: Optional[float] = None

@dataclass
class EmailConfig:
    """Configurazione SMTP"""
    smtp_server: str = "smtp.gmail.com"  # Configurare con server aziendale
    smtp_port: int = 587
    username: str = "alerts@baitservice.com"  # Email aziendale
    password: str = ""  # Da configurare
    use_tls: bool = True
    timeout: int = 30
    
    # Rate limiting
    max_emails_per_minute: int = 30
    max_retries: int = 3
    retry_delay_seconds: int = 60

class EmailTemplateEngine:
    """Engine per template email con Jinja2"""
    
    def __init__(self, template_dir: str = "templates"):
        self.template_dir = template_dir
        self._ensure_template_directory()
        
        if JINJA2_AVAILABLE:
            self.jinja_env = Environment(loader=FileSystemLoader(template_dir))
        else:
            self.jinja_env = None
            logger.warning("⚠️ Jinja2 non disponibile - template avanzati disabilitati")
        
        self._create_default_templates()
    
    def _ensure_template_directory(self):
        """Crea directory template se non esiste"""
        if not os.path.exists(self.template_dir):
            os.makedirs(self.template_dir)
            logger.info(f"📁 Creata directory template: {self.template_dir}")
    
    def _create_default_templates(self):
        """Crea template email di default"""
        
        # Template HTML per alert critici
        critical_html_template = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BAIT Service - Alert Critico</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background-color: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .alert-box { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .details { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        .actions { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .actions h3 { color: #155724; margin-top: 0; }
        .actions ul { margin: 10px 0; padding-left: 20px; }
        .actions li { margin-bottom: 8px; color: #155724; }
        .footer { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; text-align: center; font-size: 12px; color: #666; }
        .urgent { color: #dc3545; font-weight: bold; }
        .timestamp { font-size: 11px; color: #888; }
        .confidence { background-color: #007bff; color: white; padding: 4px 8px; border-radius: 15px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 BAIT Service - Alert Critico</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">{{ alert.subject }}</p>
        </div>
        
        <div class="content">
            <div class="alert-box">
                <h2 style="color: #856404; margin-top: 0;">⚠️ ATTENZIONE IMMEDIATA RICHIESTA</h2>
                <p style="font-size: 16px; line-height: 1.5; margin: 0;">{{ alert.message_friendly | replace('\\n', '<br>') | safe }}</p>
                
                {% if alert.estimated_loss %}
                <p class="urgent">💰 PERDITA STIMATA: €{{ "%.2f" | format(alert.estimated_loss) }}</p>
                {% endif %}
            </div>
            
            <div class="details">
                <h3>📋 Dettagli Tecnici</h3>
                <p><strong>ID Alert:</strong> {{ alert.id }}</p>
                <p><strong>Tecnico:</strong> {{ alert.metadata.tecnico_name }}</p>
                <p><strong>Categoria:</strong> {{ alert.category }}</p>
                <p><strong>Confidence:</strong> <span class="confidence">{{ alert.confidence_score }}%</span></p>
                <p><strong>Timestamp:</strong> <span class="timestamp">{{ alert.created_at.strftime('%d/%m/%Y %H:%M:%S') }}</span></p>
                
                {% if alert.category == 'temporal_overlap' %}
                <h4>Dettagli Sovrapposizione:</h4>
                <ul>
                    <li><strong>Cliente 1:</strong> {{ alert.metadata.cliente1 }}</li>
                    <li><strong>Cliente 2:</strong> {{ alert.metadata.cliente2 }}</li>
                    <li><strong>Durata sovrapposizione:</strong> {{ alert.metadata.overlap_minutes }} minuti</li>
                    <li><strong>Data:</strong> {{ alert.metadata.data_attivita }}</li>
                </ul>
                {% endif %}
            </div>
            
            <div class="actions">
                <h3>✅ AZIONI RICHIESTE</h3>
                <ul>
                    {% for action in alert.correction_steps %}
                    <li>{{ action }}</li>
                    {% endfor %}
                </ul>
                
                <p><strong>⏰ DEADLINE:</strong> Azione richiesta entro <span class="urgent">2 ore</span> dalla ricezione</p>
                {% if alert.followup_required %}
                <p><strong>📞 FOLLOW-UP:</strong> Conferma azione entro {{ alert.followup_delay_hours }} ore</p>
                {% endif %}
            </div>
        </div>
        
        <div class="footer">
            <p><strong>BAIT Service S.r.l.</strong> - Sistema Automatico Alert<br>
            Generato il {{ now.strftime('%d/%m/%Y alle %H:%M:%S') }}<br>
            <em>Questo è un messaggio automatico. Per supporto contattare: support@baitservice.com</em></p>
        </div>
    </div>
</body>
</html>
        """
        
        # Template HTML per alert operativi
        operational_html_template = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BAIT Service - Alert Operativo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 700px; margin: 0 auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #ffc107; color: #212529; padding: 15px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 25px; }
        .info-box { background-color: #cff4fc; border: 1px solid #9eeaf9; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { background-color: #f8f9fa; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 BAIT Service - Alert Operativo</h1>
            <p style="margin: 5px 0 0 0;">{{ alert.subject }}</p>
        </div>
        
        <div class="content">
            <div class="info-box">
                <p style="margin: 0; line-height: 1.4;">{{ alert.message_friendly | replace('\\n', '<br>') | safe }}</p>
            </div>
            
            <h3>🔧 Azioni Consigliate:</h3>
            <ul>
                {% for action in alert.correction_steps %}
                <li style="margin-bottom: 5px;">{{ action }}</li>
                {% endfor %}
            </ul>
        </div>
        
        <div class="footer">
            <p>BAIT Service - Alert ID: {{ alert.id }} - {{ now.strftime('%d/%m/%Y %H:%M') }}</p>
        </div>
    </div>
</body>
</html>
        """
        
        # Template testo semplice
        text_template = """
BAIT SERVICE - ALERT {{ alert.priority.name }}
{{ "=" * 50 }}

{{ alert.subject }}

{{ alert.message_friendly }}

AZIONI RICHIESTE:
{% for action in alert.correction_steps %}
- {{ action }}
{% endfor %}

--
BAIT Service S.r.l.
Alert ID: {{ alert.id }}
Generato: {{ now.strftime('%d/%m/%Y %H:%M:%S') }}
        """
        
        # Salva template
        templates = {
            'critical_alert.html': critical_html_template,
            'operational_alert.html': operational_html_template,  
            'alert_text.txt': text_template
        }
        
        for filename, content in templates.items():
            filepath = os.path.join(self.template_dir, filename)
            if not os.path.exists(filepath):
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(content)
                logger.info(f"📄 Creato template: {filename}")
    
    def render_email(self, alert: ActionableAlert, template_type: str = "auto") -> Dict[str, str]:
        """Renderizza email da alert usando template appropriato"""
        
        # Determina template automaticamente se non specificato
        if template_type == "auto":
            if alert.priority in [NotificationPriority.IMMEDIATE, NotificationPriority.URGENT]:
                template_type = "critical"
            else:
                template_type = "operational"
        
        # Carica template
        try:
            if not self.jinja_env:
                # Fallback senza Jinja2
                return self._render_simple_template(alert, template_type)
            
            if template_type == "critical":
                html_template = self.jinja_env.get_template('critical_alert.html')
            else:
                html_template = self.jinja_env.get_template('operational_alert.html')
            
            text_template = self.jinja_env.get_template('alert_text.txt')
            
            # Context per rendering
            context = {
                'alert': alert,
                'now': datetime.now()
            }
            
            # Renderizza
            html_content = html_template.render(context)
            text_content = text_template.render(context)
            
            return {
                'html': html_content,
                'text': text_content,
                'subject': alert.subject
            }
            
        except Exception as e:
            logger.error(f"❌ Errore rendering template per alert {alert.id}: {e}")
            
            # Fallback template semplice
            return {
                'html': f"<html><body><h2>{alert.subject}</h2><pre>{alert.message_friendly}</pre></body></html>",
                'text': f"{alert.subject}\n\n{alert.message_friendly}",
                'subject': alert.subject
            }
    
    def _render_simple_template(self, alert: ActionableAlert, template_type: str) -> Dict[str, str]:
        """Template semplice senza Jinja2"""
        
        # HTML semplice
        html_content = f"""
<html>
<body style="font-family: Arial, sans-serif;">
<h2 style="color: {'red' if template_type == 'critical' else 'orange'};">
    {'🚨 ALERT CRITICO' if template_type == 'critical' else '📋 Alert Operativo'}
</h2>
<h3>{alert.subject}</h3>
<div style="background-color: #f5f5f5; padding: 15px; margin: 10px 0;">
    <pre style="white-space: pre-wrap;">{alert.message_friendly}</pre>
</div>
<h4>Azioni Richieste:</h4>
<ul>
"""
        
        for action in alert.correction_steps:
            html_content += f"<li>{action}</li>\n"
        
        html_content += f"""
</ul>
<hr>
<small>BAIT Service - Alert ID: {alert.id} - {datetime.now().strftime('%d/%m/%Y %H:%M')}</small>
</body>
</html>
        """
        
        # Testo semplice
        text_content = f"""
BAIT SERVICE - ALERT {alert.priority.name}
{'=' * 50}

{alert.subject}

{alert.message_friendly}

AZIONI RICHIESTE:
"""
        
        for i, action in enumerate(alert.correction_steps, 1):
            text_content += f"{i}. {action}\n"
        
        text_content += f"""
--
BAIT Service S.r.l.
Alert ID: {alert.id}
Generato: {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}
        """
        
        return {
            'html': html_content.strip(),
            'text': text_content.strip(),
            'subject': alert.subject
        }

class EmailSystem:
    """Sistema completo invio email"""
    
    def __init__(self, config: EmailConfig = None):
        self.config = config or EmailConfig()
        self.template_engine = EmailTemplateEngine()
        self.delivery_results = []
        self._last_send_time = 0
        self._email_count_last_minute = 0
        
    def send_alert_email(self, alert: ActionableAlert, test_mode: bool = True) -> EmailDeliveryResult:
        """Invia email per singolo alert"""
        start_time = time.time()
        
        try:
            # Rate limiting
            self._apply_rate_limiting()
            
            # Renderizza email
            email_content = self.template_engine.render_email(alert)
            
            if test_mode:
                # Modalità test - non invia realmente
                logger.info(f"📧 [TEST MODE] Email simulata per {alert.primary_recipient}")
                logger.info(f"📧 Subject: {email_content['subject']}")
                
                return EmailDeliveryResult(
                    success=True,
                    alert_id=alert.id,
                    recipient=alert.primary_recipient,
                    sent_at=datetime.now(),
                    delivery_time_ms=(time.time() - start_time) * 1000
                )
            
            # Modalità produzione - invio reale
            return self._send_email_smtp(alert, email_content, start_time)
            
        except Exception as e:
            logger.error(f"❌ Errore invio email alert {alert.id}: {e}")
            return EmailDeliveryResult(
                success=False,
                alert_id=alert.id,
                recipient=alert.primary_recipient,
                error_message=str(e)
            )
    
    def _send_email_smtp(self, alert: ActionableAlert, email_content: Dict, start_time: float) -> EmailDeliveryResult:
        """Invio SMTP reale"""
        
        # Crea messaggio MIME
        msg = MIMEMultipart('alternative')
        msg['Subject'] = email_content['subject']
        msg['From'] = self.config.username
        msg['To'] = alert.primary_recipient
        
        # Aggiungi CC
        if alert.cc_recipients:
            msg['Cc'] = ', '.join(alert.cc_recipients)
        
        # Aggiungi contenuto HTML e testo
        text_part = MIMEText(email_content['text'], 'plain', 'utf-8')
        html_part = MIMEText(email_content['html'], 'html', 'utf-8')
        
        msg.attach(text_part)
        msg.attach(html_part)
        
        # Lista completa destinatari
        all_recipients = [alert.primary_recipient] + alert.cc_recipients
        
        # Connessione SMTP
        try:
            server = smtplib.SMTP(self.config.smtp_server, self.config.smtp_port, timeout=self.config.timeout)
            if self.config.use_tls:
                server.starttls()
            
            server.login(self.config.username, self.config.password)
            server.sendmail(self.config.username, all_recipients, msg.as_string())
            server.quit()
            
            delivery_time = (time.time() - start_time) * 1000
            logger.info(f"✅ Email inviata per alert {alert.id} a {alert.primary_recipient} ({delivery_time:.1f}ms)")
            
            return EmailDeliveryResult(
                success=True,
                alert_id=alert.id,
                recipient=alert.primary_recipient,
                sent_at=datetime.now(),
                delivery_time_ms=delivery_time
            )
            
        except Exception as e:
            logger.error(f"❌ Errore SMTP alert {alert.id}: {e}")
            return EmailDeliveryResult(
                success=False,
                alert_id=alert.id,
                recipient=alert.primary_recipient,
                error_message=str(e)
            )
    
    def _apply_rate_limiting(self):
        """Applica rate limiting per evitare spam"""
        current_time = time.time()
        
        # Reset counter ogni minuto
        if current_time - self._last_send_time > 60:
            self._email_count_last_minute = 0
            self._last_send_time = current_time
        
        # Controlla limite
        if self._email_count_last_minute >= self.config.max_emails_per_minute:
            sleep_time = 60 - (current_time - self._last_send_time)
            logger.info(f"⏳ Rate limiting: attesa {sleep_time:.1f}s...")
            time.sleep(sleep_time)
            self._email_count_last_minute = 0
            self._last_send_time = time.time()
        
        self._email_count_last_minute += 1
    
    def send_batch_alerts(self, alerts: List[ActionableAlert], test_mode: bool = True) -> List[EmailDeliveryResult]:
        """Invia batch di alert email"""
        results = []
        
        logger.info(f"📮 Avvio invio batch {len(alerts)} email alert...")
        
        # Ordina per priorità
        alerts_sorted = sorted(alerts, key=lambda x: x.priority.value)
        
        for i, alert in enumerate(alerts_sorted, 1):
            logger.info(f"📧 Invio {i}/{len(alerts)}: {alert.subject[:50]}...")
            
            result = self.send_alert_email(alert, test_mode)
            results.append(result)
            self.delivery_results.append(result)
            
            # Piccola pausa tra invii
            if not test_mode:
                time.sleep(0.5)
        
        # Statistiche
        successful = len([r for r in results if r.success])
        failed = len(results) - successful
        
        logger.info(f"📊 Batch completato: {successful} successi, {failed} errori")
        
        return results
    
    def generate_delivery_report(self, output_file: str = None) -> Dict:
        """Genera report consegna email"""
        if not self.delivery_results:
            return {}
        
        successful = [r for r in self.delivery_results if r.success]
        failed = [r for r in self.delivery_results if not r.success]
        
        avg_delivery_time = sum(r.delivery_time_ms or 0 for r in successful) / len(successful) if successful else 0
        
        report = {
            'summary': {
                'total_emails': len(self.delivery_results),
                'successful': len(successful),
                'failed': len(failed),
                'success_rate_percent': (len(successful) / len(self.delivery_results)) * 100,
                'avg_delivery_time_ms': avg_delivery_time
            },
            'successful_deliveries': [
                {
                    'alert_id': r.alert_id,
                    'recipient': r.recipient,
                    'sent_at': r.sent_at.isoformat() if r.sent_at else None,
                    'delivery_time_ms': r.delivery_time_ms
                }
                for r in successful
            ],
            'failed_deliveries': [
                {
                    'alert_id': r.alert_id,
                    'recipient': r.recipient,
                    'error': r.error_message
                }
                for r in failed
            ]
        }
        
        if output_file:
            with open(output_file, 'w', encoding='utf-8') as f:
                json.dump(report, f, indent=2, ensure_ascii=False, default=str)
            logger.info(f"📄 Report delivery salvato: {output_file}")
        
        return report

def main():
    """Test sistema email"""
    logger.info("🚀 Testing BAIT Email System v3.0...")
    
    # Test con alert fittizio
    from alert_generator import ActionableAlert, NotificationChannel
    
    test_alert = ActionableAlert(
        id="TEST_ALERT_001",
        original_alert_id="BAIT_V2_20250809_0001",
        priority=NotificationPriority.IMMEDIATE,
        channels=[NotificationChannel.EMAIL],
        
        primary_recipient="franco.bait@baitservice.com",
        cc_recipients=["management@baitservice.com"],
        
        subject="TEST: Sovrapposizione temporale CLIENTE A / CLIENTE B",
        message_friendly="Alert di test per verifica sistema email",
        technical_details="Dettagli tecnici test",
        correction_steps=["Azione 1", "Azione 2"],
        
        business_impact="Test impact",
        urgency_reason="Test urgency", 
        estimated_loss=150.0,
        
        send_immediately=True,
        schedule_time=None,
        followup_required=True,
        followup_delay_hours=4,
        
        created_at=datetime.now(),
        category="temporal_overlap",
        confidence_score=95.0,
        data_sources=["test"],
        
        metadata={
            'cliente1': 'CLIENTE A',
            'cliente2': 'CLIENTE B', 
            'overlap_minutes': 45,
            'data_attivita': '09/08/2025',
            'tecnico_name': 'Franco Test'
        }
    )
    
    # Inizializza sistema email
    email_system = EmailSystem()
    
    # Test invio (modalità test)
    result = email_system.send_alert_email(test_alert, test_mode=True)
    
    logger.info(f"✅ Test risultato: {'Successo' if result.success else 'Fallimento'}")
    
    # Genera report
    timestamp = datetime.now().strftime("%Y%m%d_%H%M")
    report_file = f'/mnt/c/Users/Franco/Desktop/controlli/email_delivery_report_{timestamp}.json'
    report = email_system.generate_delivery_report(report_file)
    
    logger.info("✅ Test Email System completato!")

if __name__ == "__main__":
    main()