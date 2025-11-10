"""
auto_seo_one.py

FastAPI によるクラウド自動投稿システムのサンプル実装です。このスクリプトは
記事の投稿データをキューに登録し、WordPress REST API を通じて自動的に公開します。

実運用ではタスクキュー（Redis や RabbitMQ など）や非同期処理、認証管理を追加してください。
"""

from __future__ import annotations

from typing import List, Dict
import os
import threading
import time

import requests
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel


app = FastAPI(title="AUTO-SEO One Queue")

# 簡易的なキュー実装。プロダクションでは別のメッセージキューを利用してください。
queue: List[Dict[str, str]] = []
queue_lock = threading.Lock()

# WordPress REST API 設定
WP_SITE_URL = os.getenv("WP_SITE_URL", "http://localhost")
WP_USERNAME = os.getenv("WP_USERNAME", "admin")
WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD", "application-password")


class PostItem(BaseModel):
    title: str
    content: str
    status: str = "draft"  # 'draft' or 'publish'


@app.post("/enqueue")
def enqueue_post(item: PostItem):
    """投稿データをキューに追加します。"""
    with queue_lock:
        queue.append(item.dict())
    return {"message": "queued", "queue_size": len(queue)}


def process_queue() -> None:
    """キュー内の投稿を順に WordPress に送信します。"""
    while True:
        with queue_lock:
            if not queue:
                time.sleep(5)
                continue
            item = queue.pop(0)
        try:
            send_to_wordpress(item)
        except Exception as exc:
            print(f"Failed to post to WordPress: {exc}")
        time.sleep(1)


def send_to_wordpress(item: Dict[str, str]) -> None:
    """WordPress REST API へ投稿を送信します。"""
    url = WP_SITE_URL.rstrip('/') + "/wp-json/wp/v2/posts"
    auth = (WP_USERNAME, WP_APP_PASSWORD)
    data = {
        "title": item["title"],
        "content": item["content"],
        "status": item.get("status", "draft"),
    }
    response = requests.post(url, auth=auth, json=data, timeout=10)
    if response.status_code not in (200, 201):
        raise HTTPException(status_code=500, detail=f"WordPress returned {response.status_code}: {response.text}")


# バックグラウンドスレッドでキューを処理する
threading.Thread(target=process_queue, daemon=True).start()