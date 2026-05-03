/**
 * Speech-to-Text Handler for Ghrami Messaging
 * Uses browser's Web Audio API + recorder.js + Groq Whisper API
 *
 * Wrapped in an IIFE to avoid polluting the global scope and conflicting
 * with functions of the same name declared in the page's inline scripts
 * (startRecording, stopRecording, handleRecordingStop, etc.).
 */
(function () {

let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;
const recordBtn = document.getElementById('chat-record-btn');
const messageInput = document.getElementById('chat-message-input');
const statusDiv = document.getElementById('chat-stt-status');
const selectedConversationUser = document.querySelector('input[name="receiver_id"]')?.value;

const STT_ENDPOINT = '/matching/stt/transcribe';
const AI_REPLY_ENDPOINT = '/matching/api/generate-reply';

function hasSttUi() {
    return Boolean(recordBtn && messageInput && statusDiv);
}

function updateStatus(text, color = '#667eea', visible = true) {
    if (!statusDiv) return;
    statusDiv.style.display = visible ? 'block' : 'none';
    statusDiv.textContent = text;
    statusDiv.style.color = color;
}

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
    if (!hasSttUi()) {
        console.warn('[STT] Missing required DOM nodes (record button, input, or status).');
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            }
        });

        // Pick best supported mimeType (Safari doesn't support webm;codecs=opus)
        const mimeType = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/mp4',
        ].find(type => MediaRecorder.isTypeSupported(type)) || '';

        mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});

        audioChunks = [];
        isRecording = true;

        // Update UI immediately after confirming recorder is ready
        recordBtn.textContent = '⏹️ Stop Recording';
        recordBtn.classList.add('btn-danger');
        recordBtn.classList.remove('btn-secondary');
        updateStatus('🎤 Recording...', '#22c55e');
        
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
        // Reset button state in case it was partially updated
        recordBtn.textContent = '🎤 Record Voice';
        recordBtn.classList.remove('btn-danger');
        recordBtn.classList.add('btn-secondary');
        isRecording = false;

        updateStatus('❌ Microphone access denied: ' + err.message, '#dc3545');
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
        
        updateStatus('⏳ Transcribing audio...', '#667eea');
        
        mediaRecorder.stop();
        isRecording = false;
    }
}

/**
 * Handle completion of recording - send to backend STT
 */
async function handleRecordingStop() {
    if (!hasSttUi()) {
        console.warn('[STT] Missing required DOM nodes while finishing recording.');
        return;
    }

    try {
        const audioBlob = new Blob(audioChunks, { type: 'audio/webm; codecs=opus' });
        
        // Show uploading status
        updateStatus('📤 Sending to Groq Whisper API...', '#667eea');
        
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
        
        updateStatus('✅ Voice transcribed successfully!', '#22c55e');
        
        // Show AI reply suggestion button
        const aiBtn = document.getElementById('chat-ai-reply-btn');
        if (aiBtn && messageInput.value) {
            aiBtn.style.display = 'inline-block';
        }
        
        // Auto-close status after 3 seconds
        setTimeout(() => {
            updateStatus('', '#667eea', false);
        }, 3000);
        
    } catch (err) {
        updateStatus('❌ Transcription failed: ' + err.message, '#dc3545');
        console.error('STT Error:', err);
    } finally {
        if (recordBtn) {
            recordBtn.textContent = '🎤 Record Voice';
            recordBtn.disabled = false;
            recordBtn.classList.add('btn-secondary');
            recordBtn.classList.remove('btn-danger');
        }
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
            updateStatus('✨ Generating AI reply suggestions...', '#667eea');
            
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
                updateStatus('✅ AI suggestion applied!', '#22c55e');
            }
            
            setTimeout(() => {
                updateStatus('', '#667eea', false);
            }, 2000);
            
        } catch (err) {
            updateStatus('❌ AI suggestion failed: ' + err.message, '#dc3545');
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

})(); // end IIFE — keeps startRecording/stopRecording/etc. out of global scope