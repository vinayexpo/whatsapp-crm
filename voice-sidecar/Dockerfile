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
        https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx.json \
    && curl -sL -o /app/voices/hi_IN-pratham-medium.onnx \
        https://huggingface.co/rhasspy/piper-voices/resolve/main/hi/hi_IN/pratham/medium/hi_IN-pratham-medium.onnx \
    && curl -sL -o /app/voices/hi_IN-pratham-medium.onnx.json \
        https://huggingface.co/rhasspy/piper-voices/resolve/main/hi/hi_IN/pratham/medium/hi_IN-pratham-medium.onnx.json \
    && curl -sL -o /app/voices/te_IN-maya-medium.onnx \
        https://huggingface.co/rhasspy/piper-voices/resolve/main/te/te_IN/maya/medium/te_IN-maya-medium.onnx \
    && curl -sL -o /app/voices/te_IN-maya-medium.onnx.json \
        https://huggingface.co/rhasspy/piper-voices/resolve/main/te/te_IN/maya/medium/te_IN-maya-medium.onnx.json

# Pre-download the sherpa-onnx multilingual Whisper model into the image so
# the first real call doesn't pay a cold-start download. SenseVoice (the
# previous engine) only covers zh/en/ja/ko/yue and has no Hindi/Telugu
# support at all, so it can't serve this flow's language requirement --
# Whisper's multilingual "small" checkpoint trades some of SenseVoice's speed
# for actual en/hi/te coverage in one model file.
RUN mkdir -p /app/stt-models \
    && curl -sL -o /tmp/whisper-small.tar.bz2 \
        https://github.com/k2-fsa/sherpa-onnx/releases/download/asr-models/sherpa-onnx-whisper-small.tar.bz2 \
    && tar -xjf /tmp/whisper-small.tar.bz2 -C /tmp \
    && mv /tmp/sherpa-onnx-whisper-small /app/stt-models/whisper-small \
    && rm /tmp/whisper-small.tar.bz2

COPY app ./app

EXPOSE 8090

CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8090}"]
