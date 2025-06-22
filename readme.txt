# Dynamic Page Translator - Guida Installazione e Configurazione

## 📋 Indice
1. [Requisiti di Sistema](#requisiti-di-sistema)
2. [Installazione](#installazione)
3. [Configurazione Iniziale](#configurazione-iniziale)
4. [Configurazione API](#configurazione-api)
5. [Personalizzazione Bandiere](#personalizzazione-bandiere)
6. [Gestione Moduli](#gestione-moduli)
7. [Ottimizzazione SEO](#ottimizzazione-seo)
8. [Risoluzione Problemi](#risoluzione-problemi)

## 🔧 Requisiti di Sistema

### Requisiti Minimi
- **WordPress:** 5.0 o superiore
- **PHP:** 7.4 o superiore
- **MySQL:** 5.6 o superiore (o MariaDB 10.0+)
- **Estensioni PHP:** cURL, JSON, mbstring
- **Memoria PHP:** 128MB (raccomandati 256MB)

### Requisiti Raccomandati
- **WordPress:** 6.0 o superiore
- **PHP:** 8.0 o superiore
- **Memoria PHP:** 512MB
- **SSL:** Certificato valido per chiamate API

## 📦 Installazione

### Metodo 1: Upload Manuale
1. Scarica il file ZIP del plugin
2. Vai su **Plugin > Aggiungi Nuovo** nel tuo admin WordPress
3. Clicca **Carica Plugin** e seleziona il file ZIP
4. Clicca **Installa Ora** e poi **Attiva**

### Metodo 2: FTP
1. Estrai il file ZIP in `/wp-content/plugins/dynamic-translator/`
2. Vai su **Plugin** nell'admin e attiva "Dynamic Page Translator"

### Metodo 3: WP-CLI
```bash
wp plugin install dynamic-translator.zip --activate
```

## ⚙️ Configurazione Iniziale

### 1. Accesso Pannello Admin
Dopo l'attivazione, troverai **"Translator"** nel menu admin di WordPress.

### 2. Impostazioni Generali
1. Vai su **Translator > Impostazioni**
2. Configura:
   - **Lingue Abilitate:** Seleziona le lingue che vuoi supportare
   - **Lingua Predefinita:** Imposta la lingua principale del sito
   - **Rilevamento Automatico:** Abilita per rilevare automaticamente la lingua del browser

### 3. Database
Il plugin creerà automaticamente le seguenti tabelle:
- `wp_dpt_translations_cache` - Cache delle traduzioni
- `wp_dpt_translation_logs` - Log delle traduzioni (opzionale)

## 🔑 Configurazione API

### Google Translate API

#### 1. Ottieni API Key
1. Vai su [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuovo progetto o selezionane uno esistente
3. Abilita l'API "Cloud Translation"
4. Vai su **Credenziali > Crea Credenziali > Chiave API**
5. Copia la chiave generata

#### 2. Configura nel Plugin
1. Vai su **Translator > Impostazioni > Provider API**
2. Seleziona **Google Translate** come provider
3. Incolla la tua API key nel campo **Google API Key**
4. Clicca **Test** per verificare la connessione
5. Salva le impostazioni

#### 3. Pricing Google Translate
- **Costo:** $20 per 1 milione di caratteri
- **Free Tier:** $300 di credito gratuito per nuovi account
- **Fatturazione:** Pay-per-use, addebitato mensilmente

### OpenRouter AI

#### 1. Ottieni API Key
1. Registrati su [OpenRouter.ai](https://openrouter.ai/)
2. Vai al tuo dashboard
3. Genera una nuova API key
4. Copia la chiave

#### 2. Configura nel Plugin
1. Vai su **Translator > Impostazioni > Provider API**
2. Seleziona **OpenRouter AI** come provider
3. Incolla la tua API key nel campo **OpenRouter API Key**
4. Seleziona il modello AI (raccomandato: Llama 3.1 8B per iniziare)
5. Clicca **Test** per verificare
6. Salva le impostazioni

#### 3. Modelli Disponibili
- **Llama 3.1 8B (Free)** - Gratuito, qualità buona
- **Llama 3.1 70B** - $0.59/1M token, qualità eccellente
- **Claude 3 Haiku** - $0.80/1M token, qualità eccellente
- **GPT-4o Mini** - $0.60/1M token, qualità molto buona

## 🏁 Personalizzazione Bandiere

### 1. Posizioni Disponibili
- **Angoli Fissi:** Top-left, Top-right, Bottom-left, Bottom-right
- **Integrazione Tema:** Header, Footer, Menu, Sidebar
- **Fluttuante:** Posizione draggabile dall'utente
- **Personalizzata:** Usa selettori CSS per posizioni specifiche

### 2. Stili Visualizzazione
- **Dropdown:** Menu a tendina classico
- **Inline:** Bandiere affiancate
- **Popup:** Finestra modale
- **Sidebar Slide:** Pannello scorrevole laterale
- **Circle Menu:** Menu circolare animato
- **Minimal:** Solo codici lingua

### 3. Configurazione Avanzata
1. Vai su **Translator > Impostazioni > Visualizzazione**
2. Seleziona posizione e stile
3. Configura opzioni avanzate:
   - Dimensione bandiere
   - Stile bordi (arrotondati, circolari)
   - Ombreggiatura
   - Animazioni
   - Nascondere su mobile

### 4. Posizioni Personalizzate
Per usare posizioni personalizzate:
1. Seleziona **"Personalizzato"** come posizione
2. Aggiungi selettori CSS (es: `.main-header`, `#navigation`)
3. Scegli il metodo di inserimento (append, prepend, after, before)

### 5. Bandiere Personalizzate
1. Vai su **Translator > Bandiere**
2. Carica bandiere personalizzate (SVG, PNG, JPG, WebP)
3. Dimensioni raccomandate: 32x24px
4. Formato preferito: SVG per scalabilità

## 🧩 Gestione Moduli

### 1. Moduli Inclusi
- **Google Translate Provider** - Traduzione via Google API
- **OpenRouter Provider** - Traduzione via AI
- **Flag Display** - Gestione visualizzazione bandiere
- **SEO Optimizer** - Ottimizzazione SEO multilingue

### 2. Installazione Moduli Esterni
1. Vai su **Translator > Moduli**
2. Clicca **Carica Modulo**
3. Seleziona file ZIP del modulo
4. Attiva il modulo dalla lista

### 3. Sviluppo Moduli Personalizzati
Struttura directory modulo:
```
/wp-content/dpt-modules/nome-modulo/
├── nome-modulo.php
├── module.json
├── README.md
└── assets/
    ├── css/
    └── js/
```

## 🔍 Ottimizzazione SEO

### 1. Configurazione SEO
1. Vai su **Translator > SEO**
2. Abilita:
   - **Tag Hreflang** - Per SEO multilingue
   - **Tag Canonical** - Evita contenuto duplicato
   - **Traduzione Meta** - Title e description automatici
   - **Schema Markup** - Dati strutturati multilingue

### 2. Struttura URL
Scegli la struttura URL preferita:
- **Parametro:** `sito.com/pagina?lang=en`
- **Sottodirectory:** `sito.com/en/pagina` (richiede configurazione server)
- **Sottodominio:** `en.sito.com/pagina` (richiede configurazione DNS)

### 3. Sitemap XML
Il plugin genera automaticamente sitemap per ogni lingua:
- `sito.com/?sitemap-lang=en`
- `sito.com/?sitemap-lang=it`

## 📊 Monitoraggio e Statistiche

### 1. Dashboard Statistiche
Vai su **Translator > Statistiche** per vedere:
- Traduzioni totali
- Traduzioni per provider
- Coppie di lingue più usate
- Utilizzo API e costi
- Performance cache

### 2. Gestione Cache
1. Vai su **Translator > Cache**
2. Funzioni disponibili:
   - Visualizza statistiche cache
   - Pulisci cache scaduta
   - Pulisci tutta la cache
   - Ottimizza database
   - Esporta/Importa cache

### 3. Log Traduzioni
Abilita il logging per monitorare:
- Richieste API
- Errori di traduzione
- Utilizzo per utente
- Performance

## 🛠️ Risoluzione Problemi

### Problemi Comuni

#### 1. Le traduzioni non funzionano
**Possibili cause:**
- API key non configurata o non valida
- Quota API esaurita
- Firewall che blocca richieste esterne
- Plugin cache aggressivo

**Soluzioni:**
1. Verifica API key in **Translator > Impostazioni > Provider API**
2. Controlla quota API nel dashboard del provider
3. Testa connessione con il pulsante **Test**
4. Disabilita temporaneamente plugin cache
5. Controlla log errori PHP

#### 2. Bandiere non si visualizzano
**Possibili cause:**
- Conflitto CSS con il tema
- JavaScript disabilitato
- Posizione personalizzata non valida

**Soluzioni:**
1. Cambia posizione in **Translator > Impostazioni > Visualizzazione**
2. Controlla console browser per errori JavaScript
3. Prova stile diverso (dropdown invece di inline)
4. Verifica selettori CSS se usi posizioni personalizzate

#### 3. Cache non funziona
**Possibili cause:**
- Database pieno
- Permessi file insufficienti
- Cache disabilitata

**Soluzioni:**
1. Vai su **Translator > Cache** e ottimizza database
2. Verifica permessi directory `wp-content`
3. Controlla che cache sia abilitata nelle impostazioni

#### 4. SEO problems
**Possibili cause:**
- Tag hreflang duplicati
- Conflitto con plugin SEO
- Struttura URL non supportata

**Soluzioni:**
1. Disabilita hreflang in altri plugin SEO
2. Usa struttura URL "parametro" per compatibilità
3. Verifica tag hreflang con Google Search Console

### Log e Debug

#### 1. Abilita Debug WordPress
Aggiungi in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

#### 2. Log Plugin
Abilita logging in **Translator > Impostazioni > Avanzate**

#### 3. Controllo File Log
I log si trovano in `/wp-content/debug.log`

### Supporto

#### 1. Documentazione
- [Documentazione completa](https://tuosito.com/dynamic-translator-docs/)
- [FAQ](https://tuosito.com/dynamic-translator-faq/)
- [Video Tutorial](https://tuosito.com/dynamic-translator-tutorials/)

#### 2. Community
- [Forum di supporto](https://tuosito.com/forum/)
- [Discord Community](https://discord.gg/dynamic-translator)
- [GitHub Issues](https://github.com/tuoaccount/dynamic-translator/issues)

#### 3. Supporto Premium
- Email: support@tuosito.com
- Live Chat: Disponibile nel dashboard del plugin
- Supporto prioritario per clienti premium

## 🚀 Ottimizzazioni Performance

### 1. Cache
- Abilita cache traduzioni (default 30 giorni)
- Usa plugin cache compatibili (WP Rocket, W3 Total Cache)
- Configura CDN per bandiere statiche

### 2. API
- Monitora utilizzo API per evitare costi eccessivi
- Usa modello AI più economico per contenuti non critici
- Abilita rate limiting per prevenire abusi

### 3. Database
- Pulisci cache scaduta regolarmente
- Ottimizza tabelle database mensilmente
- Backup traduzioni importanti

## 📝 Best Practices

### 1. Configurazione
- Inizia con poche lingue e espandi gradualmente
- Testa traduzioni su contenuto rappresentativo
- Configura correttamente la lingua predefinita

### 2. SEO
- Usa sempre tag hreflang
- Mantieni URL structure consistente
- Monitora indicizzazione con Google Search Console

### 3. User Experience
- Posiziona bandiere in modo prominente ma non invasivo
- Usa stili coerenti con il design del sito
- Testa su dispositivi mobili

### 4. Costi
- Monitora utilizzo API mensilmente
- Configura alert per quota API
- Considera modelli AI gratuiti per test

---

**Versione Guida:** 1.0.0  
**Ultimo Aggiornamento:** Giugno 2025  
**Compatibilità:** WordPress 5.0+, PHP 7.4+