FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    libavdevice-dev libavfilter-dev libopus-dev libvpx-dev pkg-config \
    build-essential curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

RUN mkdir -p /app/voices \
    && curl -sL -o /app/voices/en_US-lessac-medium.onnx \
        https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx \
    && curl -sL -o /app/voices/en_US-lessac-medium.onnx.json \
        https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx.json

# Pre-download the sherpa-onnx SenseVoice model into the image so the first
# real call doesn't pay a cold-start download. SenseVoice scales compute to
# the audio's actual length rather than always processing a padded 30s
# window like Whisper, which is what caused 5-30+ second per-utterance
# transcription times on this host's 2 CPU cores with faster-whisper.
RUN mkdir -p /app/stt-models \
    && curl -sL -o /tmp/sense-voice.tar.bz2 \
        https://github.com/k2-fsa/sherpa-onnx/releases/download/asr-models/sherpa-onnx-sense-voice-zh-en-ja-ko-yue-2024-07-17.tar.bz2 \
    && tar -xjf /tmp/sense-voice.tar.bz2 -C /tmp \
    && mv /tmp/sherpa-onnx-sense-voice-zh-en-ja-ko-yue-2024-07-17 /app/stt-models/sense-voice \
    && rm /tmp/sense-voice.tar.bz2

COPY app ./app

EXPOSE 8090

CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8090}"]
