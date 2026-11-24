=== Agente AI per WordPress ===
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Chat in bacheca con un agente (API Anthropic) con pieno accesso amministrativo al sito.

== Installazione ==

1. Plugin → Aggiungi nuovo → Carica plugin → wp-ai-agent.zip → Attiva.
2. Crea una API key su https://console.anthropic.com (a consumo, separata dall'abbonamento Claude).
3. In wp-config.php (consigliato) oppure in Agente AI → Impostazioni:
       define( 'WPAIA_API_KEY', 'sk-ant-...' );
       define( 'WPAIA_GITHUB_REPO', 'risik01/wp-agent-it' );
       define( 'WPAIA_GITHUB_TOKEN', 'github_pat_...' ); // solo se il repository è privato
4. Apri "Agente AI" nel menu laterale e scrivi.

== Cosa sa fare ==

- Impostazioni e opzioni (anche dei plugin)
- Plugin e temi: cerca, installa (da wordpress.org o da URL zip), attiva, aggiorna, elimina
- Customizer: theme mods e CSS aggiuntivo
- Menu (classici e navigazione a blocchi) e widget
- Contenuti con metadati: crea, modifica, cestina, elimina
- Utenti: crea, modifica ruoli/email/password, elimina
- Codice PHP arbitrario, query SQL, lettura/scrittura file, lettura di pagine web
- Aggiornamento di WordPress

Ogni azione di modifica chiede conferma (disattivabile in Impostazioni).
Tutte le azioni vanno nel Registro azioni; i file modificati o eliminati vengono copiati in wp-content/wpaia-backups/.

== Recupero di emergenza ==

Se un codice PHP o un file scritto dall'agente blocca il sito:
- ripristina il file da wp-content/wpaia-backups/<data>/ via FTP o file manager, oppure
- rinomina la cartella del plugin che causa l'errore in wp-content/plugins/.
