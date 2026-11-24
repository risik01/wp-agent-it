(function () {
	'use strict';
	const cfg = window.WPAIA || {};
	const box = document.getElementById('wpaia-messages');
	if (!box) return;
	const form = document.getElementById('wpaia-form');
	const input = document.getElementById('wpaia-input');
	const send = document.getElementById('wpaia-send');
	const reset = document.getElementById('wpaia-reset');
	const stop = document.getElementById('wpaia-stop');
	const pendingBox = document.getElementById('wpaia-pending');

	const LABELS = {
		get_site_info: 'Informazioni del sito', get_option: 'Lettura opzione', list_options: 'Ricerca opzioni',
		update_option: 'Modifica opzione', delete_option: 'Eliminazione opzione',
		list_plugins: 'Elenco plugin', search_plugins: 'Ricerca plugin', install_plugin: 'Installazione plugin',
		activate_plugin: 'Attivazione plugin', deactivate_plugin: 'Disattivazione plugin', update_plugin: 'Aggiornamento plugin', delete_plugin: 'Eliminazione plugin',
		list_themes: 'Elenco temi', search_themes: 'Ricerca temi', install_theme: 'Installazione tema', activate_theme: 'Attivazione tema',
		update_theme: 'Aggiornamento tema', delete_theme: 'Eliminazione tema',
		get_theme_mods: 'Lettura Customizer', set_theme_mod: 'Modifica Customizer', set_custom_css: 'CSS aggiuntivo',
		list_menus: 'Elenco menu', create_menu: 'Creazione menu', delete_menu: 'Eliminazione menu', add_menu_item: 'Voce di menu',
		delete_menu_item: 'Eliminazione voce di menu', assign_menu_location: 'Posizione menu',
		list_widgets: 'Elenco widget', add_widget: 'Aggiunta widget', update_widget: 'Modifica widget', remove_widget: 'Rimozione widget',
		list_posts: 'Elenco contenuti', get_post: 'Lettura contenuto', create_post: 'Creazione contenuto', update_post: 'Modifica contenuto', delete_post: 'Eliminazione contenuto',
		list_users: 'Elenco utenti', create_user: 'Creazione utente', update_user: 'Modifica utente', delete_user: 'Eliminazione utente',
		run_php: 'Esecuzione codice PHP', db_query: 'Query SQL', list_dir: 'Elenco cartella', read_file: 'Lettura file',
		write_file: 'Scrittura file', delete_file: 'Eliminazione file', fetch_url: 'Lettura pagina web', update_core: 'Aggiornamento WordPress'
	};

	let stopRequested = false;
	let running = false;

	async function api(path, body, method) {
		const res = await fetch(cfg.restUrl + path, {
			method: method || 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: method === 'GET' ? undefined : JSON.stringify(body || {}),
			credentials: 'same-origin'
		});
		let data;
		try { data = await res.json(); } catch (e) {
			throw new Error('Risposta non valida dal server (HTTP ' + res.status + '). Se l\'ultima azione era codice PHP o una modifica a un file, controlla il sito e il log degli errori PHP.');
		}
		if (!res.ok) throw new Error(data && data.message ? data.message : 'Errore HTTP ' + res.status);
		return data;
	}

	function esc(s) {
		return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	}

	function md(text) {
		const blocks = [];
		let h = esc(text).replace(/```[\w-]*\n?([\s\S]*?)```/g, (m, code) => { blocks.push(code); return '\u0000' + (blocks.length - 1) + '\u0000'; });
		h = h.replace(/`([^`\n]+)`/g, '<code>$1</code>')
			.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
			.replace(/^#{1,4} (.+)$/gm, '<strong>$1</strong>')
			.replace(/^[-*] (.+)$/gm, '• $1')
			.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
			.replace(/\n/g, '<br>');
		return h.replace(/\u0000(\d+)\u0000/g, (m, i) => '<pre><code>' + blocks[i] + '</code></pre>');
	}

	function shortInput(inp) {
		if (!inp || typeof inp !== 'object') return '';
		return Object.keys(inp).map(k => {
			let v = typeof inp[k] === 'string' ? inp[k] : JSON.stringify(inp[k]);
			v = v.replace(/\s+/g, ' ');
			return k + ': ' + (v.length > 70 ? v.slice(0, 70) + '…' : v);
		}).join(', ');
	}

	function add(ev) {
		const el = document.createElement('div');
		if (ev.type === 'user') {
			el.className = 'wpaia-msg wpaia-user';
			el.innerHTML = md(ev.text);
		} else if (ev.type === 'text') {
			el.className = 'wpaia-msg wpaia-bot';
			el.innerHTML = md(ev.text);
		} else if (ev.type === 'tool') {
			el.className = 'wpaia-tool' + (ev.ok ? '' : ' wpaia-tool-err');
			el.innerHTML = (ev.ok ? '⚙️ ' : '⚠️ ') + esc(LABELS[ev.name] || ev.name) +
				' <span>' + esc(shortInput(ev.input)) + '</span>' +
				(ev.summary ? '<div class="wpaia-tool-detail">' + esc(ev.summary) + '</div>' : '');
		} else {
			el.className = 'wpaia-notice' + (ev.type === 'error' ? ' wpaia-error' : '');
			el.textContent = ev.text;
			if (ev.retry) {
				const b = document.createElement('button');
				b.type = 'button'; b.className = 'button button-small'; b.textContent = ev.retryLabel || 'Riprova';
				b.addEventListener('click', () => { b.remove(); drive(() => api('continue')); });
				el.appendChild(document.createTextNode(' '));
				el.appendChild(b);
			}
		}
		box.appendChild(el);
		box.scrollTop = box.scrollHeight;
	}

	let thinkingEl = null;
	function busy(on) {
		running = on;
		send.disabled = on; input.disabled = on; reset.disabled = on;
		stop.hidden = !on; stop.disabled = false;
		if (on && !thinkingEl) {
			thinkingEl = document.createElement('div');
			thinkingEl.className = 'wpaia-thinking';
			thinkingEl.textContent = 'Sto lavorando…';
		}
		if (on) { box.appendChild(thinkingEl); box.scrollTop = box.scrollHeight; }
		else if (thinkingEl) { thinkingEl.remove(); thinkingEl = null; }
	}

	function showPending(list) {
		if (!list || !list.length) { pendingBox.hidden = true; pendingBox.innerHTML = ''; return; }
		let h = '<p><strong>L\'agente chiede conferma per:</strong></p><ul>';
		list.forEach(p => {
			let detail;
			if (p.name === 'run_php' && p.input.code) detail = p.input.code;
			else if (p.name === 'db_query' && p.input.sql) detail = p.input.sql;
			else if (p.name === 'write_file') detail = (p.input.path || '') + '\n\n' + (p.input.content || '');
			else detail = JSON.stringify(p.input, null, 2);
			h += '<li><strong>' + esc(LABELS[p.name] || p.name) + '</strong><pre>' + esc(detail) + '</pre></li>';
		});
		h += '</ul><div class="wpaia-actions"><button type="button" class="button" data-a="0">Rifiuta</button> <button type="button" class="button button-primary" data-a="1">Approva ed esegui</button></div>';
		pendingBox.innerHTML = h;
		pendingBox.hidden = false;
		send.disabled = true; input.disabled = true;
		pendingBox.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
			const ok = b.dataset.a === '1';
			showPending(null);
			add({ type: 'notice', text: ok ? 'Azione approvata.' : 'Azione rifiutata.' });
			drive(() => api('confirm', { approve: ok }));
		}));
	}

	/** Esegue la prima richiesta e poi chiama /continue finché il lavoro non è finito. */
	async function drive(first) {
		stopRequested = false;
		busy(true);
		let call = first;
		try {
			while (call) {
				const data = await call();
				(data.events || []).forEach(add);
				if (data.status === 'confirm') { busy(false); showPending(data.pending); return; }
				if (data.status !== 'continue') break;
				if (stopRequested) {
					add({ type: 'notice', text: 'Interrotto. Scrivi un messaggio per dare nuove istruzioni.', retry: true, retryLabel: 'Riprendi' });
					break;
				}
				call = () => api('continue');
			}
		} catch (err) {
			add({ type: 'error', text: err.message, retry: true });
		}
		busy(false);
		input.focus();
	}

	form.addEventListener('submit', e => {
		e.preventDefault();
		const msg = input.value.trim();
		if (!msg || running) return;
		input.value = '';
		add({ type: 'user', text: msg });
		drive(() => api('chat', { message: msg }));
	});

	input.addEventListener('keydown', e => {
		if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
	});

	stop.addEventListener('click', () => {
		stopRequested = true;
		stop.disabled = true;
		if (thinkingEl) thinkingEl.textContent = 'Mi fermo dopo il passo in corso…';
	});

	reset.addEventListener('click', async () => {
		if (!window.confirm('Cancellare la conversazione corrente?')) return;
		await api('reset');
		box.innerHTML = '';
		showPending(null);
	});

	api('history', null, 'GET').then(d => {
		(d.events || []).forEach(add);
		if (d.pending) showPending(d.pending);
		else if (d.resumable) add({ type: 'notice', text: 'Il lavoro precedente è rimasto a metà.', retry: true, retryLabel: 'Riprendi' });
	}).catch(() => {});
})();
