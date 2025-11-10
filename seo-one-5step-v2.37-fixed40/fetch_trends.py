"""
fetch_trends.py

指定されたジャンルに関連するトレンドキーワードを取得する簡易スクリプトです。
ネットワーク環境が利用可能な場合は pytrends を使って Google トレンドを取得し、
失敗した場合はジャンルごとの静的キーワードリストから返します。
"""
from __future__ import annotations

import argparse
import json
from typing import List

def get_static_trends(genre: str) -> List[str]:
    """ジャンルごとに静的な候補を返します"""
    fallback = {
        'テクノロジー': ['AI', 'IoT', 'ブロックチェーン', '5G', '量子コンピューター'],
        '健康': ['糖質制限', '腸活', 'ヨガ', '瞑想', '免疫力'],
        '旅行': ['国内旅行', '温泉', '北海道旅行', '沖縄旅行', '海外旅行'],
        'ビジネス': ['スタートアップ', '資金調達', '副業', 'DX', 'リスキリング'],
        '料理': ['レシピ', 'グルテンフリー', '発酵食品', 'ヴィーガン', 'スパイスカレー'],
    }
    # 部分一致でマッチ
    for key, words in fallback.items():
        if key in genre:
            return words
    # デフォルト
    return ['トレンド', '人気', '最新', 'おすすめ', '話題']

def get_trends_pytrends(genre: str) -> List[str]:
    """pytrends を利用してトレンドキーワードを取得します。接続できない場合は例外が発生します。"""
    try:
        from pytrends.request import TrendReq  # type: ignore
    except ImportError:
        raise RuntimeError('pytrends is not installed')
    # pytrends のセットアップ
    pytrends = TrendReq(hl='ja-JP', tz=540)
    # 日本のデイリートレンドを取得
    data = pytrends.trending_searches(pn='japan')
    # data は DataFrame、第一列にトレンドワード
    if not data.empty:
        trends = [str(x) for x in data[0].tolist()][:10]
    else:
        trends = []
    # pytrends ではジャンルフィルタはないため、そのまま返却
    return trends

def fetch_trends(genre: str) -> List[str]:
    # まず pytrends で取得を試みる
    try:
        words = get_trends_pytrends(genre)
        if words:
            return words
    except Exception:
        pass
    # 失敗したら静的リスト
    return get_static_trends(genre)

def main() -> None:
    parser = argparse.ArgumentParser(description='Fetch trending keywords for a genre')
    parser.add_argument('--genre', type=str, default='', help='Genre in Japanese')
    parser.add_argument('--json', action='store_true', help='Output JSON list')
    args = parser.parse_args()
    words = fetch_trends(args.genre)
    if args.json:
        # JSON オブジェクトとして出力
        # 後方互換のため、単なる配列ではなく {"keywords": [...]} の形式で返します
        print(json.dumps({"keywords": words}, ensure_ascii=False))
    else:
        print('\n'.join(words))

if __name__ == '__main__':
    main()