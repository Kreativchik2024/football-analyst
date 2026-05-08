@auth
<div id="chatWidget" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;">
    <button id="chatToggle" class="btn btn-primary rounded-circle shadow-lg" style="width: 60px; height: 60px;">
        💬
    </button>

    <div id="chatWindow" class="bg-white rounded-4 shadow-lg p-3" style="display: none; width: 320px; max-height: 450px; flex-direction: column;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>🤖 DeepOdds AI</strong>
            <button id="chatClose" class="btn-close"></button>
        </div>
        <div id="chatMessages" class="flex-grow-1 overflow-auto mb-2" style="max-height: 300px; font-size: 0.9rem;"></div>
        <div class="input-group">
            <input type="text" id="chatInput" class="form-control form-control-sm" placeholder="Задайте вопрос...">
            <button class="btn btn-outline-secondary btn-sm" id="chatSend">➤</button>
        </div>
    </div>
</div>

<style>
    #chatMessages p { margin-bottom: 0.3rem; }
    .user-message { text-align: right; color: #0d6efd; }
    .ai-message { text-align: left; color: #333; }
</style>

<script>
    const chatToggle = document.getElementById('chatToggle');
    const chatWindow = document.getElementById('chatWindow');
    const chatClose = document.getElementById('chatClose');
    const chatInput = document.getElementById('chatInput');
    const chatSend = document.getElementById('chatSend');
    const chatMessages = document.getElementById('chatMessages');

    chatToggle.addEventListener('click', () => {
        chatWindow.style.display = 'flex';
        chatToggle.style.display = 'none';
    });
    chatClose.addEventListener('click', () => {
        chatWindow.style.display = 'none';
        chatToggle.style.display = 'block';
    });

    function addMessage(text, sender) {
        const p = document.createElement('p');
        p.textContent = text;
        p.className = sender === 'user' ? 'user-message' : 'ai-message';
        chatMessages.appendChild(p);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function sendQuestion() {
        const question = chatInput.value.trim();
        if (!question) return;

        addMessage(question, 'user');
        chatInput.value = '';

        // Получаем CSRF-токен из мета-тега (более надёжно)
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        try {
            const res = await fetch('{{ route("chat.ask") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ question })
            });

            if (!res.ok) {
                console.error('HTTP Error', res.status, await res.text());
                addMessage('Ошибка сервера (' + res.status + ')', 'ai');
                return;
            }

            const data = await res.json();
            addMessage(data.answer || 'Пустой ответ', 'ai');
        } catch (e) {
            console.error('Fetch error', e);
            addMessage('Ошибка связи: ' + e.message, 'ai');
        }
    }

    chatSend.addEventListener('click', sendQuestion);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendQuestion();
    });
</script>
@endauth