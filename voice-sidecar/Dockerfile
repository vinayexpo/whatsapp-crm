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

# Pre-download the faster-whisper model into the image so the first real
# call doesn't pay a cold-start download. small.en took 5-12x realtime to
# transcribe on this host's 2 CPU cores (a 5s utterance took ~12s), which
# stacked into 30+ second silences mid-call and caused callers to hang up
# before the flow ever advanced past the greeting -- base.en was the fix
# at the time. distil-large-v3 replaces base.en here: it's a distilled
# version of large-v3 that runs roughly 6x faster than the full model on
# CPU (mozilla-ai/openai benchmarks put it close to small.en's latency)
# while scoring much closer to large-v3's accuracy, so it's worth the
# tradeoff over base.en for this call-flow/caller-speech use case. If this
# still proves too slow on live 2-core traffic, fall back to
# distil-whisper/distil-small.en or base.en.
RUN python -c "from faster_whisper import WhisperModel; WhisperModel('Systran/faster-distil-whisper-large-v3', device='cpu', compute_type='int8', download_root='/app/whisper-models')"

COPY app ./app

EXPOSE 8090

CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8090}"]
