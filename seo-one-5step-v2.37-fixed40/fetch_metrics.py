"""
fetch_metrics.py

Google Search Console および Google Analytics 4 から指標を取得し、WordPress の
REST API を通じてプラグインに送信するユーティリティスクリプトです。サービス
アカウント認証を使用しています。実行には `google-api-python-client` と
`google-analytics-data` のインストールが必要です。

使用手順:

1. Google Cloud コンソールでサービスアカウントを作成し、Search Console API
   と Analytics Data API のスコープを有効にした JSON キーをダウンロード。
2. サービスアカウントに対して、Search Console のプロパティにアクセス権を付与
   （Search Console 管理画面でユーザー追加）、および GA4 プロパティに閲覧権限
   を付与します。
3. このスクリプトの環境変数または設定変数を編集して、WordPress のエンド
   ポイントや認証情報、プロパティ ID を設定します。
4. `pip install google-api-python-client google-analytics-data requests` を実行して
   依存ライブラリをインストールします。
5. スクリプトを実行すると、最新のメトリクスが取得され WordPress に送信されます。
"""

from __future__ import annotations

import os
import datetime
import json
import argparse
from typing import Dict, Any

import requests
from google.oauth2 import service_account
from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import DateRange, Dimension, Metric, RunReportRequest
from googleapiclient.discovery import build


# 設定: 環境変数で上書きできます
SERVICE_ACCOUNT_FILE = os.getenv('GOOGLE_APPLICATION_CREDENTIALS', 'service-account.json')
GA4_PROPERTY_ID = os.getenv('GA4_PROPERTY_ID', 'YOUR-GA4-PROPERTY-ID')
GSC_SITE_URL = os.getenv('GSC_SITE_URL', 'https://example.com/')

WP_BASE_URL = os.getenv('WP_SITE_URL', 'http://localhost')
WP_USERNAME = os.getenv('WP_USERNAME', 'admin')
WP_APP_PASSWORD = os.getenv('WP_APP_PASSWORD', 'application-password')

WP_ENDPOINT = f"{WP_BASE_URL.rstrip('/')}/wp-json/seoone/v1/update-metrics"


def get_service_account_credentials(scopes: list[str]):
    return service_account.Credentials.from_service_account_file(
        SERVICE_ACCOUNT_FILE,
        scopes=scopes,
    )


def fetch_ga4_metrics(creds) -> Dict[str, Any]:
    """GA4 データ API から sessions、activeUsers、conversions を取得します。"""
    client = BetaAnalyticsDataClient(credentials=creds)
    today = datetime.date.today()
    start_date = (today - datetime.timedelta(days=30)).isoformat()
    end_date = today.isoformat()
    request = RunReportRequest(
        property=f"properties/{GA4_PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start_date, end_date=end_date)],
        metrics=[
            Metric(name="sessions"),
            Metric(name="activeUsers"),
            Metric(name="conversions"),
        ],
    )
    response = client.run_report(request)
    metrics = {
        'sessions': 0,
        'active_users': 0,
        'conversions': 0,
    }
    if response.rows:
        row = response.rows[0]
        metrics['sessions'] = int(row.metric_values[0].value)
        metrics['active_users'] = int(row.metric_values[1].value)
        metrics['conversions'] = int(row.metric_values[2].value)
    return metrics


def fetch_gsc_metrics(creds) -> Dict[str, Any]:
    """Search Console API からクリック数・表示回数・CTR・平均順位を取得します。"""
    service = build('searchconsole', 'v1', credentials=creds)
    today = datetime.date.today()
    start_date = (today - datetime.timedelta(days=30)).isoformat()
    end_date = today.isoformat()
    body = {
        'startDate': start_date,
        'endDate': end_date,
        'dimensions': ['query'],
        'rowLimit': 250,
    }
    response = service.searchanalytics().query(siteUrl=GSC_SITE_URL, body=body).execute()
    clicks = impressions = ctr_sum = position_sum = 0.0
    rows = response.get('rows', [])
    # prepare list for top queries
    query_rows = []
    for row in rows:
        row_clicks = row.get('clicks', 0)
        row_impr   = row.get('impressions', 0)
        row_ctr    = row.get('ctr', 0)
        row_pos    = row.get('position', 0)
        clicks += row_clicks
        impressions += row_impr
        ctr_sum += row_ctr
        position_sum += row_pos
        query_rows.append({
            'query': row.get('keys', [''])[0] if row.get('keys') else '',
            'clicks': row_clicks,
            'impressions': row_impr,
            'ctr': row_ctr,
            'position': row_pos
        })
    total = len(rows) if rows else 1
    # sort queries by clicks descending
    top_queries = sorted(query_rows, key=lambda x: x['clicks'], reverse=True)[:10]
    metrics = {
        'clicks': int(clicks),
        'impressions': int(impressions),
        'ctr': ctr_sum / total,
        'position': position_sum / total,
        'top_queries': top_queries,
    }
    return metrics


def update_wordpress(metrics: Dict[str, Any]) -> None:
    """WordPress にメトリクスを送信します。"""
    response = requests.post(
        WP_ENDPOINT,
        auth=(WP_USERNAME, WP_APP_PASSWORD),
        json=metrics,
        timeout=10,
    )
    if response.status_code not in (200, 201):
        raise Exception(f"Failed to update WordPress: {response.status_code} {response.text}")


def main() -> None:
    parser = argparse.ArgumentParser(description='Fetch metrics from GA4 and Search Console.')
    parser.add_argument('--json', action='store_true', help='Print metrics as JSON instead of updating WordPress.')
    args = parser.parse_args()

    # Search Console のスコープ
    gsc_scopes = ['https://www.googleapis.com/auth/webmasters.readonly']
    # GA4 のスコープ
    ga4_scopes = ['https://www.googleapis.com/auth/analytics.readonly']
    creds = get_service_account_credentials(gsc_scopes + ga4_scopes)

    gsc_metrics = fetch_gsc_metrics(creds)
    ga4_metrics = fetch_ga4_metrics(creds)
    metrics = { **gsc_metrics, **ga4_metrics }
    if args.json:
        print(json.dumps(metrics))
    else:
        update_wordpress(metrics)
        print('Metrics updated:', metrics)


if __name__ == '__main__':
    main()