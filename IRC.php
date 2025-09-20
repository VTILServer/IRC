<?php
// ---------------------------------------------------------------- //
// Made by: ErringPaladin10 (ErringPaladin10@VTILServer.com)
// Creation Date: 09/19/2025
// Last Updated: 09/20/2025
// ---------------------------------------------------------------- //

$apiKey = "F0BA8E63-1CC5-4709-8D30-C2089B5A46E9"; // the key from the main endpoint
$sessionId = uniqid("VTIL.", true); // unique per visitor (new session each load)
$defaultChannel = "Place2";
?>

<!DOCTYPE html>
<html lang="en">

<head data-session-id="<?php echo $sessionId?>">
  <meta charset="UTF-8">
  <title>Void Script Builder IRC</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f7f7f7;
      margin: 0;
      padding: 0;
    }

    #chat-container {
      display: flex;
      flex-direction: column;
      height: 100vh;
      margin: auto;
      border: 1px solid #ccc;
      background: white;
    }

    #messages {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }

    .msg {
      padding: 6px 8px;
      margin: 4px 0;
      border-radius: 6px;
      background: #f1f1f1;
    }

    .msg .icon {
      font-weight: bold;
      margin-right: 6px;
    }

    .msg .user {
      font-weight: bold;
      color: #0074D9;
    }

    #input-bar {
      display: flex;
      border-top: 1px solid #ccc;
      padding: 8px;
    }

    #input-bar input[type=text] {
      flex: 1;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    #input-bar button {
      margin-left: 8px;
      padding: 8px 14px;
      border: none;
      border-radius: 4px;
      background: #0074D9;
      color: white;
      cursor: pointer;
    }

    #input-bar button:hover {
      background: #005fa3;
    }

    #channel-select {
      margin: 8px;
    }
  </style>
</head>

<body>
  <div id="chat-container">
    <div id="channel-select">
      Channel:
      <select id="channel">
        <option value="Place1" selected>Place1</option>
        <option value="Place2" selected>Place2</option>
      </select>
    </div>

    <div id="messages"></div>

    <div id="input-bar">
      <input type="text" id="msgInput" placeholder="Type a message...">
      <button id="sendBtn">Send</button>
    </div>
  </div>

  <script>
    const apiUrl = "PHP.php"; // the endpoint
    const apiKey = "<?php echo $apiKey; ?>";
    const sessionId = "<?php echo $sessionId; ?>";

    let channelId = "<?php echo $defaultChannel; ?>";
    let lastMessageCount = 0;

    async function fetchMessages() {
      try {
        let form = new FormData();
        form.append("ApiKey", apiKey);
        form.append("SessionId", sessionId);
        form.append("ChannelId", channelId);
        form.append("Messages", ""); // no new message, just fetch
        form.append("RequestEmojis", "true");

        let res = await fetch(apiUrl, { method: "POST", body: form });
        let data = await res.json();

        if (data.messages) {
          renderMessages(data.messages);
        }
      } catch (err) {
        console.error("Fetch error:", err);
      }
    }

    function renderMessages(messages) {
      const box = document.getElementById("messages");
      box.innerHTML = "";

      messages.forEach(m => {
        const div = document.createElement("div");
        div.className = "msg";
        div.innerHTML = `<span class="icon">${m.icon}</span> 
                     <span class="user">${m.speaker}:</span> 
                     <span class="text">${escapeHtml(m.message)}</span>`;
        box.appendChild(div);
      });

      box.scrollTop = box.scrollHeight;
    }

    function escapeHtml(str) {
      return str.replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])
      );
    }

    async function sendMessage() {
      const input = document.getElementById("msgInput");
      let msg = input.value.trim();
      if (!msg) return;
      input.value = "";

      let form = new FormData();
      form.append("ApiKey", apiKey);
      form.append("SessionId", sessionId);
      form.append("ChannelId", channelId);
      form.append("Messages", msg);
      form.append("RequestEmojis", "true");

      let res = await fetch(apiUrl, { method: "POST", body: form });
      let data = await res.json();

      if (data.messages) {
        renderMessages(data.messages);
      }
    }

    document.getElementById("sendBtn").addEventListener("click", sendMessage);
    document.getElementById("msgInput").addEventListener("keypress", e => {
      if (e.key === "Enter") sendMessage();
    });

    document.getElementById("channel").addEventListener("change", e => {
      channelId = e.target.value;
      fetchMessages();
    });

    // Poll messages every 2s
    setInterval(fetchMessages, 2000);
    fetchMessages();
  </script>
</body>

</html>