# Agente AI per WordPress

Plugin che aggiunge alla bacheca di WordPress una chat con un agente (API Anthropic) che amministra il sito: impostazioni, plugin, temi, Customizer, menu, widget, contenuti, utenti, codice PHP, SQL e file.

## Pubblicare un aggiornamento

I siti con il plugin installato si aggiornano dalle **release** di questo repository.

1. Modifica il codice.
2. Alza la versione nell'intestazione di `wp-ai-agent.php` (`Version: 0.2.1`).
3. Fai commit e push su `main`, poi crea il tag con lo stesso numero, in uno dei due modi:
   - **da GitHub:** Releases → *Draft a new release* → *Choose a tag* → scrivi `v0.2.1` → *Create new tag* → *Publish release*;
   - **da terminale:**

     ```bash
     git tag v0.2.1
     git push --tags
     ```

4. La GitHub Action `Release` controlla che tag e versione coincidano, verifica la sintassi PHP, crea `wp-ai-agent.zip` e pubblica la release.
5. WordPress vede l'aggiornamento entro 12 ore, oppure subito con **Plugin → Agente AI → Controlla aggiornamenti**. Con gli aggiornamenti automatici attivi lo installa da solo.

## Configurazione sui siti

In `wp-config.php`:

```php
define( 'WPAIA_API_KEY', 'sk-ant-...' );
define( 'WPAIA_GITHUB_REPO', 'risik01/wp-agent-it' );
define( 'WPAIA_GITHUB_TOKEN', 'github_pat_...' ); // solo se il repository è privato
```

Per un repository privato crea un token *fine-grained* (GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens) limitato a questo repository, con permesso **Contents: Read-only**.

## Struttura

| File | Contenuto |
|---|---|
| `wp-ai-agent.php` | Intestazione, impostazioni, pagina di amministrazione, endpoint REST |
| `includes/class-wpaia-agent.php` | Ciclo dell'agente (un passo per richiesta HTTP, nessun timeout) |
| `includes/class-wpaia-tools.php` | Strumenti a disposizione dell'agente |
| `includes/class-wpaia-updater.php` | Aggiornamenti dalle release GitHub |
| `assets/` | Interfaccia della chat |
