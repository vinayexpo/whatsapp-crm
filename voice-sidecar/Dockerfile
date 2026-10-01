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

# Pre-download the Moonshine ONNX model into the image so the first real
# call doesn't pay a cold-start download. Moonshine's base model is built to
# run fast on resource-constrained CPUs (it scales compute to the audio's
# actual length instead of always processing a padded 30s window like
# Whisper), which fixed the 5-30+ second per-utterance transcription times
# this host's 2 CPU cores hit with faster-whisper (base.en and smaller).
RUN python -c "from moonshine_onnx import MoonshineOnnxModel, load_tokenizer; MoonshineOnnxModel(model_name='moonshine/base'); load_tokenizer()"

COPY app ./app

EXPOSE 8090

CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8090}"]
