/**
 * Speech-to-Text Handler for Ghrami Messaging
 * Uses browser's Web Audio API + recorder.js + Groq Whisper API
 */

let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;
const recordBtn = document.getElementById('chat-record-btn');
const messageInput = document.getElementById('chat-message-input');
const statusDiv = document.getElementById('chat-stt-status');
const selectedConversationUser = document.querySelector('input[name="receiver_id"]')?.value;

const STT_ENDPOINT = '/matching/stt/transcribe';
const AI_REPLY_ENDPOINT = '/matching/api/generate-reply';

/**
 * Initialize recording when button is clicked
 */
if (recordBtn) {
    recordBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        if (isRecording) {
            // Stop recording
            stopRecording();
        } else {
            // Start recording
            await startRecording();
        }
    });
}

/**
 * Start audio recording from microphone
 */
async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            }
        });
        
        mediaRecorder = new MediaRecorder(stream, {
            mimeType: 'audio/webm;codecs=opus'
        });
        
        audioChunks = [];
        isRecording = true;
        
        // Update UI
        recordBtn.textContent = '⏹️ Stop Recording';
        recordBtn.classList.add('btn-danger');
        recordBtn.classList.remove('btn-secondary');
        statusDiv.style.display = 'block';
        statusDiv.textContent = '🎤 Recording...';
        statusDiv.style.color = '#22c55e';
        
        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.push(event.data);
            }
        };
        
        mediaRecorder.onstop = async () => {
            await handleRecordingStop();
        };
        
        mediaRecorder.start();
        
    } catch (err) {
        statusDiv.style.display = 'block';
        statusDiv.textContent = '❌ Microphone access denied: ' + err.message;
        statusDiv.style.color = '#dc3545';
        console.error('Error accessing microphone:', err);
    }
}

/**
 * Stop audio recording and send to Groq Whisper
 */
function stopRecording() {
    if (mediaRecorder && isRecording) {
        recordBtn.textContent = '⏳ Processing...';
        recordBtn.disabled = true;
        recordBtn.classList.add('btn-secondary');
        recordBtn.classList.remove('btn-danger');
        
        statusDiv.textContent = '⏳ Transcribing audio...';
        statusDiv.style.color = '#667eea';
        
        mediaRecorder.stop();
        isRecording = false;
    }
}

/**
 * Handle completion of recording - send to backend STT
 */
async function handleRecordingStop() {
    try {
        const audioBlob = new Blob(audioChunks, { type: 'audio/webm; codecs=opus' });
        
        // Show uploading status
        statusDiv.textContent = '📤 Sending to Groq Whisper API...';
        statusDiv.style.color = '#667eea';
        
        const formData = new FormData();
        formData.append('audio', audioBlob, 'audio.webm');
        
        const response = await fetch(STT_ENDPOINT, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`STT failed: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        // Populate message input with transcribed text
        messageInput.value = result.text || '';
        messageInput.focus();
        
        statusDiv.textContent = '✅ Voice transcribed successfully!';
        statusDiv.style.color = '#22c55e';
        
        // Show AI reply suggestion button
        const aiBtn = document.getElementById('chat-ai-reply-btn');
        if (aiBtn && messageInput.value) {
            aiBtn.style.display = 'inline-block';
        }
        
        // Auto-close status after 3 seconds
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 3000);
        
    } catch (err) {
        statusDiv.textContent = '❌ Transcription failed: ' + err.message;
        statusDiv.style.color = '#dc3545';
        console.error('STT Error:', err);
    } finally {
        recordBtn.textContent = '🎤 Record Voice';
        recordBtn.disabled = false;
        recordBtn.classList.add('btn-secondary');
        recordBtn.classList.remove('btn-danger');
    }
}

/**
 * Handle AI reply suggestions (Groq LLaMA)
 */
const aiReplyBtn = document.getElementById('chat-ai-reply-btn');
if (aiReplyBtn) {
    aiReplyBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        if (!selectedConversationUser) {
            alert('Select a conversation first');
            return;
        }
        
        try {
            aiReplyBtn.disabled = true;
            aiReplyBtn.textContent = '⏳ Generating...';
            statusDiv.style.display = 'block';
            statusDiv.textContent = '✨ Generating AI reply suggestions...';
            statusDiv.style.color = '#667eea';
            
            const response = await fetch(AI_REPLY_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    conversation_user_id: selectedConversationUser,
                    _csrf_token: document.querySelector('input[name="_csrf_token"]')?.value
                })
            });
            
            if (!response.ok) {
                throw new Error(`AI request failed: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.text) {
                messageInput.value = result.text;
                statusDiv.textContent = '✅ AI suggestion applied!';
                statusDiv.style.color = '#22c55e';
            }
            
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 2000);
            
        } catch (err) {
            statusDiv.textContent = '❌ AI suggestion failed: ' + err.message;
            statusDiv.style.color = '#dc3545';
            console.error('AI Reply Error:', err);
        } finally {
            aiReplyBtn.disabled = false;
            aiReplyBtn.textContent = '✨ AI Suggest';
        }
    });
}

// Check if mediaDevices is available
if (!navigator.mediaDevices) {
    console.error('getUserMedia not supported in this browser');
    if (recordBtn) {
        recordBtn.disabled = true;
        recordBtn.title = 'Microphone not available in this browser';
    }
}
