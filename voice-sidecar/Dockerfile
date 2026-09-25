FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    libavdevice-dev libavfilter-dev libopus-dev libvpx-dev pkg-config \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app ./app

EXPOSE 8090

CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8090}"]
