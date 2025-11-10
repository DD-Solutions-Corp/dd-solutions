<?php
/**
 * 記事生成ロジック（5ステップ構成）
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SeoOne_Generator {

  public function generate_article( $settings = array() ){
    // タイトルの決定: ユーザー指定があればそれを使用し、なければキーワードや汎用タイトルを用いる。
    if ( ! empty( $settings['title'] ) ) {
      $title = sanitize_text_field( $settings['title'] );
    } else {
      // キーワードからタイトルを生成。最初のキーワードを使って記事タイトルを構築します。
      if ( ! empty( $settings['keywords'] ) ) {
        $kw_first = sanitize_text_field( $settings['keywords'] );
        // 複数キーワードの場合は最初の要素を取得
        $kw_parts = array_map( 'trim', explode( ',', $kw_first ) );
        $kw_main = $kw_parts[0];
        $title = $kw_main . 'に関する記事';
      } else {
        $title = '自動生成記事';
      }
    }

    // 記事に必ず含めたい内容。AI に自然に織り交ぜてもらうため、プロンプトに追加します。
    $force_content = '';
    if ( ! empty( $settings['force_content'] ) ) {
      // タグを許可せず改行を維持
      $force_content = trim( wp_kses_post( $settings['force_content'] ) );
    }

    // 新しい設定値
    $keywords   = isset( $settings['keywords'] ) ? sanitize_text_field( $settings['keywords'] ) : '';
    // 除外キーワード
    $exclude    = isset( $settings['exclude_keywords'] ) ? sanitize_text_field( $settings['exclude_keywords'] ) : '';
    // convert exclude list to array
    $exclude_list = array();
    if ( ! empty( $exclude ) ) {
      $exclude_list = array_map( 'trim', explode( ',', $exclude ) );
    }
    $word_count = isset( $settings['word_count'] ) ? intval( $settings['word_count'] ) : 5000;
    if ( $word_count < 1000 ) $word_count = 1000;
    // 設定オプションを最初に読み込み
    //
    // 注: generate_article() 内では $opt を参照することが多くありますが、元の実装では
    // $opt の定義よりも先に参照してしまっていたため未定義変数の警告が出ることがありました。
    // この位置で get_option() を呼び出し、$opt を初期化しておくことで全体で一貫して利用できます。
    $opt = get_option('seoone_settings', array());

    // ペルソナ情報
    $persona_gender    = $settings['persona_gender']    ?? '';
    $persona_age       = $settings['persona_age']       ?? '';
    $persona_character = $settings['persona_character'] ?? '';
    $persona_likes     = $settings['persona_likes']     ?? '';
    $persona_dislikes  = $settings['persona_dislikes']  ?? '';
    $persona_hobbies   = $settings['persona_hobbies']   ?? '';
    $persona_origin    = $settings['persona_origin']    ?? '';
    // ペルソナ固有のトピックキーワード（あれば）
    $persona_topics    = '';
    // 言語設定（デフォルトは日本語）
    $language   = isset( $settings['language'] ) ? $settings['language'] : 'ja';
    $schedule   = isset( $settings['schedule'] ) ? $settings['schedule'] : '';

    // --- 複数ペルソナと重み付け ---
    // 設定で複数ペルソナが定義されていて、フォームで指定がない場合は重み付けで自動選択します。
    // ここでは $opt に格納されている persona_profiles のデータ形式が JSON 文字列でも PHP 配列でも
    // 誤動作なく扱えるように統一処理を行います。
    $no_persona_specified = empty( $persona_gender ) && empty( $persona_age ) && empty( $persona_character ) && empty( $persona_likes ) && empty( $persona_dislikes ) && empty( $persona_hobbies ) && empty( $persona_origin );
    if ( $no_persona_specified ) {
      // オプションにペルソナデータが存在する場合にのみ処理
      if ( isset( $opt['persona_profiles'] ) && ! empty( $opt['persona_profiles'] ) ) {
        $raw_profiles = $opt['persona_profiles'];
        // JSON 文字列の場合は decode して配列に。すでに配列の場合はそのまま採用。
        $profiles = null;
        if ( is_array( $raw_profiles ) ) {
          $profiles = $raw_profiles;
        } elseif ( is_string( $raw_profiles ) && trim( $raw_profiles ) !== '' ) {
          $decoded = json_decode( $raw_profiles, true );
          if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $profiles = $decoded;
          }
        }
        // 配列が得られた場合のみ重み付けロジックを適用
        if ( is_array( $profiles ) && ! empty( $profiles ) ) {
          // 合計重みを計算（重みが存在しない場合は 0 とみなす）。
          $total_weight = 0;
          foreach ( $profiles as $p ) {
            $w = isset( $p['weight'] ) ? floatval( $p['weight'] ) : 0;
            $total_weight += $w;
          }
          // 重みの合計が 0 以上のときのみランダムに選択
          if ( $total_weight > 0 ) {
            // 合計が 1 を超える場合でも正規化して使用
            $rand = mt_rand() / mt_getrandmax();
            $cum = 0;
            foreach ( $profiles as $p ) {
              $w = isset( $p['weight'] ) ? floatval( $p['weight'] ) : 0;
              // $total_weight で割ることで正規化
              $cum += $total_weight > 0 ? $w / $total_weight : 0;
              if ( $rand <= $cum ) {
                // 選ばれたペルソナから情報を設定
                $persona_gender    = $p['gender']    ?? '';
                $persona_age       = $p['age']       ?? '';
                $persona_character = $p['character'] ?? '';
                $persona_likes     = $p['likes']     ?? '';
                $persona_dislikes  = $p['dislikes']  ?? '';
                $persona_hobbies   = $p['hobbies']   ?? '';
                $persona_origin    = $p['origin']    ?? '';
                $persona_topics    = isset( $p['topics'] ) ? sanitize_text_field( $p['topics'] ) : '';
                break;
              }
            }
          }
        }
      }
    }

    // ペルソナ固有トピックがある場合、キーワードに追加
    if ( ! empty( $persona_topics ) ) {
      if ( empty( $keywords ) ) {
        $keywords = $persona_topics;
      } else {
        $keywords .= ', ' . $persona_topics;
      }
    }

    // ジャンル指定があり、キーワードが空の場合はトレンドキーワードを取得
    $genre = isset( $settings['genre'] ) ? sanitize_text_field( $settings['genre'] ) : '';
    if ( empty( $keywords ) && ! empty( $genre ) ) {
      // fetch_trends.py のパスを算出
      $script_path = dirname( dirname( __FILE__ ) ) . '/fetch_trends.py';
      if ( file_exists( $script_path ) ) {
        // shell 引数を安全にエスケープ
        $genre_arg = escapeshellarg( $genre );
        $cmd = 'python3 ' . escapeshellcmd( $script_path ) . ' --genre ' . $genre_arg . ' --json 2>/dev/null';
        $json = shell_exec( $cmd );
        if ( ! empty( $json ) ) {
          $data = json_decode( $json, true );
          if ( is_array( $data ) && isset( $data['keywords'] ) && is_array( $data['keywords'] ) ) {
            // キーワード配列をカンマ区切りに
            $kw_list = array_map( 'sanitize_text_field', $data['keywords'] );
            // 除外キーワードをフィルタ
            if ( ! empty( $exclude_list ) ) {
              $kw_list = array_filter( $kw_list, function($k) use ($exclude_list) {
                foreach ( $exclude_list as $ex ) {
                  if ( stripos( $k, $ex ) !== false ) {
                    return false;
                  }
                }
                return true;
              });
            }
            $keywords = implode( ', ', $kw_list );
          }
        }
      }
    }

    // 競合分析ステップ: ユーザーがチェックした場合に実行します。
    $include_competitor = ! empty( $settings['include_competitor'] );
    $competitor_notes = '';
    if ( $include_competitor && ! empty( $keywords ) ) {
      // run competitor analysis using helper method
      $competitor_notes = $this->run_competitor_analysis( $keywords, $language, $opt );
    }
    // ユーザー指定キーワードにも除外ワードを適用
    if ( ! empty( $keywords ) && ! empty( $exclude_list ) ) {
      $kw_arr = array_map( 'trim', explode( ',', $keywords ) );
      $kw_arr = array_filter( $kw_arr, function($k) use ($exclude_list) {
        foreach ( $exclude_list as $ex ) {
          if ( stripos( $k, $ex ) !== false ) {
            return false;
          }
        }
        return true;
      });
      $keywords = implode( ', ', $kw_arr );
    }

    // 既定設定
    $opt = get_option('seoone_settings', array());
    $api_key    = $opt['ai_api_key'] ?? '';
    // 各ステップ用デフォルトモデルを配列で準備。未設定の場合は共通の ai_model を利用
    // ステップごとの既定モデルを定義します。空欄の場合や設定が存在しない場合は、最適構成に基づくデフォルトを適用します。
    $default_models = array(
      // 情報取得および競合分析には Perplexity の検索連携モデルを優先使用します。
      'retrieval' => $opt['ai_model_retrieval'] ?? $opt['ai_model'] ?? 'perplexity',
      // 初稿生成には Claude Sonnet 4.5 を使用し、自然な文体と構成力を確保します。
      'draft'     => $opt['ai_model_draft']     ?? $opt['ai_model'] ?? 'claude-sonnet-4.5',
      // コーディング/整形ステップには GPT‑5 Pro を使用してキーワード最適化や文体整形を行います。
      'coding'    => $opt['ai_model_coding']    ?? $opt['ai_model'] ?? 'gpt-5-pro',
      // 採点・要約には Claude Sonnet 4.5 を使用します。
      'scoring'   => $opt['ai_model_scoring']   ?? $opt['ai_model'] ?? 'claude-sonnet-4.5',
      // ファクトチェックには Claude Opus 4.1 を使用し、長文推論と整合性評価を行います。
      'factcheck' => $opt['ai_model_fact']      ?? $opt['ai_model'] ?? 'claude-opus-4.1'
    );

    // 投稿メタ（記事単位の5ステップ設定）
    $pipeline = array();

    // ------ 既存記事タイトルの取得 -----
    // キーワードが指定されている場合、同じキーワードを含む既存記事タイトルを取得して、重複を避けるようAIに指示します。
    $existing_titles = array();
    if ( ! empty( $keywords ) ) {
      $existing_titles = $this->get_existing_titles_by_keywords( $keywords, 3 );
    }
    $current = get_post();
    if ( $current ) {
      $pipeline = get_post_meta( $current->ID, '_seoone_pipeline', true );
      if ( ! is_array($pipeline) ) $pipeline = array();
    }

    // utilities
    $pick = function($step, $key, $fallback){
      if ( isset($GLOBALS['_seoone_pick_cache_'.$step.'_'.$key]) ) { /* noop */ }
      $v = $fallback;
      if ( isset($pipeline['steps'][$step][$key]) && $pipeline['steps'][$step][$key] !== '' ) {
        $v = $pipeline['steps'][$step][$key];
      }
      return $v;
    };

    // モデル名のエイリアスをOpenRouter用のモデルIDに変換するマッピング
    $model_aliases = array(
        // OpenAI models
        'gpt-4o'             => 'openai/gpt-4o',
        'gpt-4o-mini'        => 'openai/gpt-4o-mini',
        'gpt-4-turbo'        => 'openai/gpt-4-turbo',
        'gpt-3.5-turbo'      => 'openai/gpt-3.5-turbo',
        // Anthropic models
        'claude-3-sonnet'    => 'anthropic/claude-3-sonnet-20240229',
        'claude-3-haiku'     => 'anthropic/claude-3-haiku-20240307',
        // Additional Anthropic variant (Sonnet 4.5) if available
        'claude-sonnet-4.5'  => 'anthropic/claude-sonnet-4.5',
        // Google Gemini
        'gemini-2.5-flash'   => 'google/gemini-1.5-flash',
        // DeepSeek
        'deepseek-coder-v2'  => 'deepseek-ai/deepseek-coder-v2',
        // Perplexity Sonar (search-based)
        'perplexity-sonar'   => 'perplexity/sonar',
        // Additional aliases for Perplexity and Gemini. The generic "perplexity" maps to the Sonar model, and
        // "gemini" and "gemini-pro" map to Google's Gemini Pro variant. These aliases make it easier for users to select
        // these models in the settings UI without specifying a version.
        'perplexity'        => 'perplexity/sonar',
        'gemini'            => 'google/gemini-pro',
        'gemini-pro'        => 'google/gemini-pro',
        'deepseek-coder-v2' => 'deepseek/deepseek-coder-v2',
        'perplexity-sonar'  => 'perplexity/sonar',
        // Newer models and updated endpoints
        'claude-opus-4.1'   => 'anthropic/claude-opus-4.1',
        'gpt-5-pro'         => 'openai/gpt-5-pro',
        'gpt-5-image'       => 'openai/gpt-5-image',
        // Backwards compatibility: map older Opus key to latest version
        'claude-3-opus'     => 'anthropic/claude-opus-4.1',
    );
    $on = function($step, $default=false){
      return isset($pipeline['steps'][$step]['on']) ? (bool)$pipeline['steps'][$step]['on'] : (bool)$default;
    };

    $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    $headers  = array(
      'Authorization' => 'Bearer ' . $api_key,
      'Content-Type'  => 'application/json',
    );
    // 言語に応じてシステムメッセージを変更
    switch ( $language ) {
      case 'en':
        $sys = 'You are a helpful assistant that writes high quality English blog posts.';
        break;
      case 'zh':
        $sys = 'You are a helpful assistant that writes high quality Chinese blog posts.';
        break;
      default:
        $sys = 'You are a helpful assistant that writes high quality Japanese blog posts.';
        break;
    }

    $ai_outputs = array();
    $content = '';
    $summary = '';

    // 0) 情報取得（retrieval）
    $retrieval_notes = '';
    if ( $on('retrieval') && ! empty($api_key) ) {
      $model_key = $pick('retrieval','model', $default_models['retrieval']);
      $model = $model_aliases[$model_key] ?? $model_key;
      $temp  = floatval($pick('retrieval','temp','0.2'));
      $max   = intval($pick('retrieval','max','1000'));
      // 言語ごとにリトリーバル用プロンプトを変更
      switch ( $language ) {
        case 'en':
          $prompt = 'For the following topic, provide the latest key points in bullet points from reliable sources. Add a brief source URL at the end of each item: ' . $title;
          break;
        case 'zh':
          $prompt = '请就以下主题，从可靠来源以要点形式列出最新信息，并在每一条末尾附上简短的来源URL：' . $title;
          break;
        default:
          $prompt = '以下のテーマについて、信頼ソースから最新の要点を箇条書きで。各項目の末尾に簡単な出典URLも付与：' . $title;
          break;
      }
      $body = array(
        'model'=> $model,
        'messages'=>array(
          array('role'=>'system','content'=>$sys),
          array('role'=>'user','content'=>$prompt),
        ),
        'max_tokens'=>$max, 'temperature'=>$temp
      );
      $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>60) );
      if ( ! is_wp_error($resp) && 200===wp_remote_retrieve_response_code($resp) ) {
        $j = json_decode( wp_remote_retrieve_body($resp), true );
        if ( isset($j['choices'][0]['message']['content']) ) {
          $retrieval_notes = $j['choices'][0]['message']['content'];
          $ai_outputs['retrieval'] = $retrieval_notes;
        }
      }
    }

    // 競合分析ノートを retrieval_notes に統合します
    if ( ! empty( $competitor_notes ) ) {
      if ( ! empty( $retrieval_notes ) ) {
        $retrieval_notes .= "\n\n[競合分析]\n" . $competitor_notes;
      } else {
        $retrieval_notes = "[競合分析]\n" . $competitor_notes;
      }
      // update AI outputs for retrieval to include competitor notes
      $ai_outputs['retrieval'] = $retrieval_notes;
    }

    // 1) 初稿（draft）
    if ( ! empty($api_key) ) {
      $model_key = $pick('draft','model', $default_models['draft']);
      $model = $model_aliases[$model_key] ?? $model_key;
      $temp  = floatval($pick('draft','temp','0.3'));
      $max   = intval($pick('draft','max','4096'));
      // 生成の指示を言語ごとにカスタマイズ
      // ペルソナ指示
      // ペルソナ指示を設定。人物像を文体や語彙に反映させますが、自己紹介には含めないようにします。
      $persona_instruction = '';
      if ( $language === 'en' ) {
        $attrs = array();
        if ( ! empty( $persona_age ) )       $attrs[] = $persona_age . '-year-old';
        if ( ! empty( $persona_gender ) )    $attrs[] = $persona_gender;
        if ( ! empty( $persona_origin ) )    $attrs[] = 'from ' . $persona_origin;
        if ( ! empty( $persona_character ) ) $attrs[] = 'personality ' . $persona_character;
        if ( ! empty( $persona_likes ) )     $attrs[] = 'likes ' . $persona_likes;
        if ( ! empty( $persona_dislikes ) )  $attrs[] = 'dislikes ' . $persona_dislikes;
        if ( ! empty( $persona_hobbies ) )   $attrs[] = 'hobbies ' . $persona_hobbies;
        if ( ! empty( $attrs ) ) {
          // In English mode, instruct the AI to adopt the persona implicitly. The listed traits should shape the tone and
          // perspective of the article, but must never be explicitly stated or used as a direct self‑introduction.
          $persona_instruction = 'Write from the perspective of a person with the following attributes. Do not list these details directly; instead, let them subtly guide the tone and viewpoint of your writing: ' . implode( ', ', $attrs ) . '.';
        }
      } elseif ( $language === 'zh' ) {
        $attrs = array();
        if ( ! empty( $persona_age ) )       $attrs[] = $persona_age . '岁';
        if ( ! empty( $persona_gender ) )    $attrs[] = '性别' . $persona_gender;
        if ( ! empty( $persona_origin ) )    $attrs[] = '来自' . $persona_origin;
        if ( ! empty( $persona_character ) ) $attrs[] = '性格' . $persona_character;
        if ( ! empty( $persona_likes ) )     $attrs[] = '喜欢' . $persona_likes;
        if ( ! empty( $persona_dislikes ) )  $attrs[] = '讨厌' . $persona_dislikes;
        if ( ! empty( $persona_hobbies ) )   $attrs[] = '爱好' . $persona_hobbies;
        if ( ! empty( $attrs ) ) {
          // For Chinese, request the AI to write from the viewpoint of the persona without plainly enumerating their traits.  These
          // characteristics should quietly inform the style and perspective rather than appear as an explicit self introduction.
          $persona_instruction = '请以具有以下属性的人的视角撰写文章，但不要直接列举这些细节，让这些属性自然地影响文风和视角：' . implode( '，', $attrs ) . '。';
        }
      } else {
        // Japanese
        $attrs = array();
        if ( ! empty( $persona_age ) )       $attrs[] = $persona_age . '歳';
        if ( ! empty( $persona_gender ) )    $attrs[] = '性別' . $persona_gender;
        if ( ! empty( $persona_origin ) )    $attrs[] = $persona_origin . '出身';
        if ( ! empty( $persona_character ) ) $attrs[] = '性格' . $persona_character;
        if ( ! empty( $persona_likes ) )     $attrs[] = '好きなもの' . $persona_likes;
        if ( ! empty( $persona_dislikes ) )  $attrs[] = '嫌いなもの' . $persona_dislikes;
        if ( ! empty( $persona_hobbies ) )   $attrs[] = '趣味' . $persona_hobbies;
        if ( ! empty( $attrs ) ) {
          // For Japanese, have the AI embody the persona and allow the following traits to influence tone and viewpoint.  These
          // characteristics must not be directly spelled out or used as a first‑person self introduction; instead they should be
          // woven subtly into the writing style.
          $persona_instruction = '以下の人物像になりきって自然な口調で記事を書いてください。これらの情報を直接表現せず、文体や視点にさりげなく反映させてください: ' . implode( '、', $attrs ) . '。';
        }
      }
      // キーワード指示
      $keyword_instruction = '';
      if ( ! empty( $keywords ) ) {
        if ( $language === 'en' ) {
          // Emphasise that these keywords define the main topic of the article.  The entire article should revolve around
          // these keywords, covering their meaning and related information comprehensively, while distributing them
          // evenly and naturally.
          $keyword_instruction = "\n- Use the following keywords as the core theme of the article. The article should be built around these topics, covering their meaning and related information thoroughly, while distributing them evenly and naturally: " . $keywords;
        } elseif ( $language === 'zh' ) {
          // 指出这些关键词是文章的核心主题，内容应围绕它们展开，并全面涵盖其含义及相关信息，同时在全文自然均匀地出现。
          $keyword_instruction = "\n- 将以下关键词作为文章的核心主题，围绕这些关键词展开，全面涵盖其含义及相关信息，并在全文中均匀自然地出现: " . $keywords;
        } else {
          // これらのキーワードを記事の中心テーマとして扱います。意味や関連情報を網羅的に取り上げ、記事全体を通じて均等に自然に散りばめてください。
          $keyword_instruction = "\n- 以下のキーワードを記事の中心テーマとして扱い、その意味や関連情報を網羅的に取り上げながら、記事全体を通じて均等に自然に散りばめてください: " . $keywords;
        }
      }

      // 名前と職業が指定されている場合、記事冒頭に自己紹介を含めるよう指示します。
      $intro_instruction = '';
      if ( ! empty( $persona_name ) || ! empty( $persona_role ) ) {
        if ( $language === 'en' ) {
          if ( ! empty( $persona_name ) && ! empty( $persona_role ) ) {
            $intro_instruction = 'Begin the article with a brief self-introduction using the provided name and occupation, for example: "Hello, my name is ' . $persona_name . ' and I am a ' . $persona_role . '." Do not explicitly mention other persona details.';
          } elseif ( ! empty( $persona_name ) ) {
            $intro_instruction = 'Begin the article with a brief self-introduction using the provided name, for example: "Hello, my name is ' . $persona_name . '." Do not explicitly mention other persona details.';
          } else {
            $intro_instruction = 'Begin the article with a brief self-introduction using the provided occupation, for example: "Hello, I am a ' . $persona_role . '." Do not explicitly mention other persona details.';
          }
        } elseif ( $language === 'zh' ) {
          if ( ! empty( $persona_name ) && ! empty( $persona_role ) ) {
            $intro_instruction = '在文章开头用提供的姓名和职业做一个简短的自我介绍，例如：“大家好，我叫' . $persona_name . '，是一名' . $persona_role . '”。不要提及其他性格或背景细节。';
          } elseif ( ! empty( $persona_name ) ) {
            $intro_instruction = '在文章开头用提供的姓名做一个简短的自我介绍，例如：“大家好，我叫' . $persona_name . '”。不要提及其他性格或背景细节。';
          } else {
            $intro_instruction = '在文章开头用提供的职业做一个简短的自我介绍，例如：“大家好，我是一名' . $persona_role . '”。不要提及其他性格或背景细节。';
          }
        } else {
          // Japanese (default)
          if ( ! empty( $persona_name ) && ! empty( $persona_role ) ) {
            $intro_instruction = '記事の冒頭では、提供された名前と職業を使って簡単な自己紹介をしてください（例：「こんにちは、私の名前は' . $persona_name . 'です。' . $persona_role . 'として働いています。」）。他の属性は明示しないでください。';
          } elseif ( ! empty( $persona_name ) ) {
            $intro_instruction = '記事の冒頭では、提供された名前を使って簡単な自己紹介をしてください（例：「こんにちは、私の名前は' . $persona_name . 'です。」）。他の属性は明示しないでください。';
          } else {
            $intro_instruction = '記事の冒頭では、提供された職業を使って簡単な自己紹介をしてください（例：「こんにちは、' . $persona_role . 'です。」）。他の属性は明示しないでください。';
          }
        }
      }
      // 記事に必ず含めたい内容をプロンプトに追加
      // force_content は generate_article() の先頭で設定されています
      if ( $force_content ) {
        if ( $language === 'en' ) {
          $keyword_instruction .= "\n- Please naturally incorporate the following content into the article: " . $force_content;
        } elseif ( $language === 'zh' ) {
          $keyword_instruction .= "\n- 请将以下内容以自然的口吻融入文章: " . $force_content;
        } else {
          // default Japanese
          $keyword_instruction .= "\n- 以下の内容を記事のトーンに合わせて自然に織り交ぜてください: " . $force_content;
        }
      }
      // メインプロンプト
      if ( $language === 'en' ) {
        // ジャンルが指定されている場合、記事全体の大枠として扱うよう指示
        $genre_instruction = '';
        if ( ! empty( $genre ) ) {
          $genre_instruction = "\n- Use the following genre as the overarching theme of the article, and treat the keywords as specific topics within this genre: " . $genre;
        }
        // Build the prompt for an English article.  Avoid referencing SEO or plugin names and instruct the AI to write as a
        // professional writer.  Add guidance to discourage repetition and to include new information in each section.
        $prompt = 'You are a skilled writer. Create an English article following these guidelines:' .
          "\n- The main body should be at least " . intval($word_count) . " words." .
          "\n- Avoid repeating the same content; ensure each section introduces unique information and details." .
          "\n- Begin with a summary (300-500 words) and reading time (500 words/min)." .
          // 目次はプラグイン側で自動生成されるため、AI には目次を生成させない
          "" .
          "\n- Add an image caption before each H2." .
          "\n- End with FAQ/CTA." .
          "\n- Craft a compelling and descriptive title that clearly conveys the main benefit of the article and incorporates the keywords. Use a problem×result×condition format to hook readers." .
          "\n- Organise H2 headings by topic or priority. Within each section, provide a clear conclusion, step‑by‑step instructions, decision guidelines, cost estimates, and a call to action." .
          "\n- Use specific numbers, examples, locations, and credible sources to reinforce each section and enhance E‑E‑A‑T." .
          "\n- Immediately after each H2, insert an Answer Card listing 3–5 key takeaways as bullet points to improve AI retrieval." .
          "\n- Include at least three internal links to related posts and at least one external authoritative reference, woven naturally into the text." .
          "\n- Provide a reference list or citation section at the end of the article." .
          "\n- Write descriptive image captions (alt text) that clearly explain the scene or action shown." .
          "\n- At the very beginning of your response, write the article title prefaced with 'Title:' so it can be extracted." .
          "\n- Use <h2> tags for main headings and <h3> tags for subheadings; avoid using numbered lists as headings." .
          "\n- Ensure each H2 section contains at least 500 words of content to provide sufficient depth." .
          // Region-specific examples: use specified region instead of persona origin
          ( ! empty( $region )
            ? "\n- Use examples and references from the specified region (" . $region . ") instead of the persona's place of origin, to provide relevant local context."
            : "\n- Avoid using the persona's place of origin when selecting examples or references; use general sources or the region you specify." ) .
          // 自己紹介指示がある場合はここに追加
          ( $intro_instruction ? "\n- " . $intro_instruction : '' ) .
          $genre_instruction .
          ( $persona_instruction ? "\n- " . $persona_instruction : '' ) .
          $keyword_instruction .
          "\nTopic: " . $title;
        if ( $retrieval_notes ) $prompt .= "\n\nReference notes:\n" . $retrieval_notes;

        // 既存記事への重複回避指示を追加
        if ( ! empty( $existing_titles ) ) {
          $duplicate_instruction = "\n- The following articles already exist on our site: " . implode( ', ', $existing_titles ) . ". Provide new perspectives and avoid duplicating their content.";
          $prompt .= $duplicate_instruction;
        }
      } elseif ( $language === 'zh' ) {
        // ジャンルが指定されている場合、記事の大主题として扱うように指示
        $genre_instruction = '';
        if ( ! empty( $genre ) ) {
          $genre_instruction = "\n- 将以下类别作为文章的大主题，并将关键词视为该主题内的具体子话题: " . $genre;
        }
        // Build the prompt for a Chinese article.  Do not mention SEO or the plugin and emphasise professional writing and
        // non‑repetitive sections.  Include detailed guidelines for high‑quality content and LLMO/GEO optimisation.
        $prompt = '您是一位专业的撰稿人。按照以下方针撰写中文文章：' .
          "\n- 正文不少于" . intval($word_count) . "字。" .
          "\n- 避免重复内容，每一节都要引入新的信息和细节。" .
          "\n- 开头提供摘要（300-500字）和阅读时间（按每分钟500字计算）。" .
          // 目录生成はプラグイン側で自動実装されるため、AIには生成指示しない
          "" .
          "\n- 每个H2之前添加图片说明。" .
          "\n- 最后添加FAQ/CTA。" .
          // 以下の指示でLLMO/GEO向けに品質を高めます
          "\n- 设计一个吸引人的标题，用问题×结果×条件的形式概括文章的价值并自然融入关键词。" .
          "\n- 按主题或优先级组织H2章节，每个章节中依次提供明确的结论、操作步骤、决策标准、成本估算和行动呼吁。" .
          "\n- 使用具体的数字、示例、地名和权威来源增强每个部分的可信度，提升E-E-A-T。" .
          "\n- 在每个H2之后立即插入3–5条要点的Answer Card，便于AI提取摘要。" .
          "\n- 自然地插入至少三个内部链接和一个外部权威参考。" .
          "\n- 在文章末尾提供参考来源列表或引用部分。" .
          "\n- 为每张图片编写描述性alt文字，清楚解释图像中的场景或动作。" .
          "\n- 在文章开头以“标题：”加上文章标题，便于抽取。" .
          "\n- 对主要章节使用<h2>标签，子章节使用<h3>标签，不要用编号列表作为标题。" .
          "\n- 每个H2章节的内容不少于500字，以确保充分的信息量。" .
          "\n- 每个H2章节的内容不少于500字，以确保充分的信息量。" .
          // 地域の例や参考をペルソナの出身地ではなく指定された地域に基づかせる指示
          ( ! empty( $region )
            ? "\n- 使用案例和参考资料时，优先采用指定地区（" . $region . "）的资料，而不要引用人物的出生地。"
            : "\n- 避免引用人物出生地的案例或数据，如未指定地区则使用一般性来源。" ) .
          ( $intro_instruction ? "\n- " . $intro_instruction : '' ) .
          $genre_instruction .
          ( $persona_instruction ? "\n- " . $persona_instruction : '' ) .
          $keyword_instruction .
          "\n主题: " . $title;
        if ( $retrieval_notes ) $prompt .= "\n\n参考笔记:\n" . $retrieval_notes;

        // 既存記事への重複回避指示を追加
        if ( ! empty( $existing_titles ) ) {
          $duplicate_instruction_cn = "\n- 以下标题的文章已存在于本网站：" . implode( ', ', $existing_titles ) . "。请确保您的文章提供新的视角，并避免内容重复。";
          $prompt .= $duplicate_instruction_cn;
        }
      } else {
        // 日本語
        // ジャンルが指定されている場合、記事の大枠のテーマとして扱うように指示
        $genre_instruction = '';
        if ( ! empty( $genre ) ) {
          $genre_instruction = "\n- 次のジャンルを大枠のテーマとして扱い、その中にキーワードを含めてください: " . $genre;
        }
        // Build the prompt for a Japanese article.  Frame the AI as a skilled writer instead of an SEO editor and add
        // instructions to avoid repetition and include fresh details in each section.  Include extended guidelines for SEO/LLMO/GEO.
        $prompt = 'あなたは優れた記事ライターです。以下の方針で日本語の記事を作成してください：'.
          "\n- 本文は" . intval($word_count) . "文字以上です。".
          "\n- 同じ内容を繰り返さないようにし、各セクションで新しい情報や詳細を盛り込んでください。".
          "\n- 冒頭に要約（300〜500字）と読了時間（500字/分）を記載してください。".
          "" .
          "\n- 各H2の前に画像説明文を追加してください。".
          "\n- 最後にFAQとCTAを追加してください。" .
          // ここからLLMO/GEOに適した詳細指示
          "\n- 読者を惹きつける魅力的なタイトルを作成し、問題×結果×条件の形式で記事の価値を伝え、キーワードを自然に組み込みます。" .
          "\n- タイトルに『最新版』や年号を入れる場合は、現在の年（" . date_i18n('Y') . "年）を使用し、過去の年を記載しないでください。" .
          "\n- H2見出しはテーマまたは優先度で整理し、それぞれの章で結論、具体的な手順、判断基準、費用目安、CTAを順に示してください。" .
          "\n- 各セクションでは具体的な数値、事例、地名、信頼できる情報源などを用いてE-E-A-Tを向上させます。" .
          "\n- 各H2直後に3〜5項目のAnswer Cardを箇条書きで挿入し、AIが要点を抽出しやすくします。" .
          "\n- 本文中に関連する記事への内部リンクを3件以上、外部の信頼できる資料へのリンクを1件以上自然に含めてください。" .
          "\n- 記事の最後に参考文献や引用リストを掲載してください。" .
          "\n- 画像のaltテキストはシーンや動作が分かるよう具体的に記述してください。" .
          "\n- 記事冒頭に「タイトル：」を付けてタイトルを記載してください。" .
          "\n- 見出しには<h2>タグ（大見出し）、<h3>タグ（小見出し）を使用し、番号付きリストを見出しにしないでください。" .
          "\n- 各H2セクションは500文字以上としてください。" .
          "\n- H2の章構成は次の流れを参考にし、記事全体を網羅してください：基本知識や定義、選び方や判断基準、方法・手順、注意点やトラブル対処、応用や関連情報、FAQ、まとめとCTA。" .
          "\n- 各H2章の冒頭には3〜5行の要点箇条書きを追加し、続いて500文字以上の詳細な解説と200文字以上の具体例やケーススタディを含めてください。" .
          "\n- 文章は「です・ます」調で、1文は60文字以内を目安とし、段落ごとに一つの話題に絞り、まず・次に・一方でなどの接続詞を適度に挿入して読みやすさを保ちます。" .
          
          "\n- 各H2およびH3見出しには必ずHTMLタグを使用し、<h2 id=\"h2-1\">タイトル</h2>、<h3 id=\"h3-1-1\">小見出し</h3> のように id 属性を連番で付与してください。Markdownの # 記号は使用しません。" .
          /* 目次生成はプラグイン側で自動化されるため、AIには生成させない */ '' .
          "\n- Answer Card は <div class=\"answer-card\"><ul><li>要点1</li><li>要点2</li>...</ul></div> の形で装飾してください。" .
          "\n- FAQ セクションでは各質問と回答を <details class=\"faq-item\"><summary>質問</summary><p>回答...</p></details> の形式で出力し、質問をクリックすると回答が展開されるようにしてください。" .
          "\n- 参考文献リストは記事の末尾に <div class=\"references\"><ul><li><a href=\"URL\">文献タイトル</a></li>...</ul></div> の形式で掲載し、各文献にリンクを付けてください。" .
          "\n- 記事中には関連する内部リンクを少なくとも3件、外部リンクを少なくとも1件含め、自然な文脈で挿入してください。" .
          ( ! empty( $region )
            ? "\n- 参考文献や具体例は、ペルソナの出身地ではなく、指定された地域（" . $region . "）の情報を優先してください。"
            : "\n- ペルソナの出身地に基づく情報ではなく、一般的または指定地域の事例や資料を用いてください。" ) .
          ( $intro_instruction ? "\n- " . $intro_instruction : '' ) .
          $genre_instruction .
          ( $persona_instruction ? "\n- " . $persona_instruction : '' ) .
          $keyword_instruction .
          "\nテーマ: " . $title;
        if ( $retrieval_notes ) $prompt .= "\n\n参考ノート:\n".$retrieval_notes;

        // 既存記事への重複回避指示を追加
        if ( ! empty( $existing_titles ) ) {
          $duplicate_instruction_ja = "\n- 以下のタイトルの記事が当サイトに既に存在します。これらと内容が重複しないように、新しい視点や詳細を盛り込んでください: " . implode( ', ', $existing_titles );
          $prompt .= $duplicate_instruction_ja;
        }
      }
      $body = array(
        'model'=> $model,
        'messages'=>array(
          array('role'=>'system','content'=>$sys),
          array('role'=>'user','content'=>$prompt),
        ),
        'max_tokens'=>$max, 'temperature'=>$temp
      );
      // API へリクエスト
      $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>120) );
      // 応答コードを確認し、生成に失敗した場合はフォールバックモデルで再試行します。
      if ( ! is_wp_error($resp) && 200 === wp_remote_retrieve_response_code($resp) ) {
        $j = json_decode( wp_remote_retrieve_body( $resp ), true );
        $content = $j['choices'][0]['message']['content'] ?? '';
        $ai_outputs['draft'] = $content;
      } else {
        // エラー取得
        $error_msg = '';
        if ( is_wp_error( $resp ) ) {
          $error_msg = $resp->get_error_message();
        } else {
          $error_body = wp_remote_retrieve_body( $resp );
          if ( is_string( $error_body ) && $error_body !== '' ) {
            $error_msg = $error_body;
          } else {
            $error_msg = 'Unknown error';
          }
        }
        /*
         * エラーが発生した場合はフォールバックモデルへの自動切り替えを行わず、
         * 記事本文にエラー内容を出力するように変更しました。ユーザーが何が起きたのかを
         * 直接確認できるようにするためです。これにより、どのモデルで失敗したのかが
         * 分からなくなる問題を防ぎます。
         */
        // エラー内容を保存
        $ai_outputs['draft_error'] = 'API error: ' . $error_msg;
        // 本文にもエラー内容を出力する
        $content = '<p>AI生成に失敗しました: ' . esc_html( $error_msg ) . '</p>';
      }
    }

    // --- 生成された初稿の長さをチェックし、指定の文字数/語数を満たすまで追記生成を繰り返します ---
    // 記事の長さが目安より短い場合、AI に追加の内容を求めます。
    if ( ! empty( $content ) && ! empty( $api_key ) ) {
      // 現在の長さを計算する関数。日本語・中国語は文字数、英語は単語数で評価します。
      $seoone_compute_length = function( $txt ) use ( $language ) {
        $plain = wp_strip_all_tags( $txt );
        if ( $language === 'en' ) {
          // PHP の str_word_count は英字と数字のみを単語として数えるため、英語の語数推定として利用
          return str_word_count( $plain );
        } else {
          // 日本語およびその他の言語ではマルチバイト文字数をカウント
          return mb_strlen( $plain, 'UTF-8' );
        }
      };
      $current_len = $seoone_compute_length( $content );
      // 追加生成の試行回数を増やします。草案段階でもより長文を出力できるようにするため、
      // 4回まで追記生成を試みます。
      $max_attempts = 4;
      $attempt = 0;
      while ( $current_len < $word_count && $attempt < $max_attempts ) {
        // draft 用モデル・パラメータを改めて取得
        $draft_model_key = $pick('draft','model', $default_models['draft'] );
        $draft_model     = $model_aliases[ $draft_model_key ] ?? $draft_model_key;
        $draft_temp      = floatval( $pick('draft','temp','0.3') );
        $draft_max       = intval( $pick('draft','max','4096') );
        // 追加生成のためのプロンプトを言語ごとに準備
        if ( $language === 'en' ) {
          $cont_prompt = 'Continue the article and expand it until the total length is at least ' . intval( $word_count ) . ' words. Do not repeat previous content; add new perspectives and details. Current article: ' . $content;
        } elseif ( $language === 'zh' ) {
          $cont_prompt = '继续扩充文章，直到总长度不少于' . intval( $word_count ) . '字。不要重复前文，加入新的观点和细节。文章：' . $content;
        } else {
          // Japanese (default)
          $cont_prompt = '記事を続けて拡充し、合計で' . intval( $word_count ) . '文字以上になるようにしてください。元の内容を繰り返さず、新しい視点や詳細を追加してください。本文: ' . $content;
        }
        $body_extra = array(
          'model'     => $draft_model,
          'messages'  => array(
            array( 'role' => 'system', 'content' => $sys ),
            array( 'role' => 'user',   'content' => $cont_prompt ),
          ),
          'max_tokens'  => $draft_max,
          'temperature' => $draft_temp,
        );
        // API へリクエスト
        $resp_extra = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body_extra ), 'timeout' => 120 ) );
        if ( ! is_wp_error( $resp_extra ) && 200 === wp_remote_retrieve_response_code( $resp_extra ) ) {
          $j_extra = json_decode( wp_remote_retrieve_body( $resp_extra ), true );
          $extra_content = $j_extra['choices'][0]['message']['content'] ?? '';
          if ( $extra_content ) {
            // 追記した内容を本文の末尾に追加
            $content .= "\n\n" . $extra_content;
            $attempt++;
            // 追記内容を記録
            $ai_outputs[ 'draft_extension_' . $attempt ] = $extra_content;
            // 長さを再計算
            $current_len = $seoone_compute_length( $content );
            continue;
          }
        }
        // 失敗または追記がない場合はループを抜ける
        break;
      }
    }

    // 2) コーディング/編集（coding）: HTML整形・内部リンク・構造化のヒント指示（ここでは簡易校正）
    if ( $on('coding') && ! empty($api_key) && $content ){
      $model_key = $pick('coding','model', $default_models['coding']);
      $model = $model_aliases[$model_key] ?? $model_key;
      $temp  = floatval($pick('coding','temp','0.2'));
      $max   = intval($pick('coding','max','1500'));
      $prompt = '次の本文を見出しID付与、目次生成前提の整形、不要な重複除去、文法修正を行って返してください。本文のみ：\n\n' . $content;
      $body = array(
        'model'=> $model,
        'messages'=>array(
          array('role'=>'system','content'=>$sys),
          array('role'=>'user','content'=>$prompt),
        ),
        'max_tokens'=>$max, 'temperature'=>$temp
      );
      $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>120) );
      if ( ! is_wp_error($resp) && 200===wp_remote_retrieve_response_code($resp) ) {
        $j = json_decode( wp_remote_retrieve_body($resp), true );
        $edited = $j['choices'][0]['message']['content'] ?? '';
        if ( $edited ) $content = $edited;
        $ai_outputs['coding'] = $edited;
      }
    }

    // 3) 採点（scoring）
    if ( $on('scoring') && ! empty($api_key) && $content ){
      $model_key = $pick('scoring','model', $default_models['scoring']);
      $model = $model_aliases[$model_key] ?? $model_key;
      $temp  = floatval($pick('scoring','temp','0.0'));
      $max   = intval($pick('scoring','max','800'));
      $prompt = '次の本文を「網羅性/日本語/SEO/YMYL」の4軸で10点満点採点し、改善TODOを箇条書きで。JSONで返す：{scores:{coverage,ja,seo,ymyl},todo:[...] } 本文: ' . $content;
      $body = array(
        'model'=> $model,
        'messages'=>array(
          array('role'=>'system','content'=>$sys),
          array('role'=>'user','content'=>$prompt),
        ),
        'max_tokens'=>$max, 'temperature'=>$temp
      );
      $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>60) );
      if ( ! is_wp_error($resp) && 200===wp_remote_retrieve_response_code($resp) ) {
        $j = json_decode( wp_remote_retrieve_body($resp), true );
        $score = $j['choices'][0]['message']['content'] ?? '';
        if ( $current ) update_post_meta( $current->ID, '_seoone_score_report', $score );
        $ai_outputs['scoring'] = $score;
        // 採点結果の平均スコア（0-100）を算出します。JSON形式で scores:{coverage,ja,seo,ymyl} が返ることを想定しています。
        $numeric_score = 100;
        $score_data = json_decode( $score, true );
        if ( is_array($score_data) && isset($score_data['scores']) ) {
          $total = 0;
          $cnt = 0;
          foreach ( $score_data['scores'] as $val ) {
            $total += floatval( $val );
            $cnt++;
          }
          if ( $cnt > 0 ) {
            $avg = $total / $cnt;
            $numeric_score = $avg * 10; // 10点満点→100点換算
          }
        }
        // スコアに応じて再生成または再コーディングを実行します。最大1回の改善を行います。
        $attempt_improve = 0;
        $max_improve = 1;
        while ( $numeric_score < 90 && $attempt_improve < $max_improve ) {
          if ( $numeric_score < 70 ) {
            // 70未満の場合は初稿の書き直し（網羅性を高めるために再生成）
            $draft_model_key = $pick('draft','model', $default_models['draft'] );
            $draft_model     = $model_aliases[ $draft_model_key ] ?? $draft_model_key;
            $draft_temp      = floatval( $pick('draft','temp','0.3') );
            $draft_max       = intval( $pick('draft','max','4096') );
            if ( $language === 'en' ) {
              $improve_prompt = 'Rewrite and expand the following article to improve coverage and clarity, addressing missing points and adding new information while avoiding repetition. Article: ' . $content;
            } elseif ( $language === 'zh' ) {
              $improve_prompt = '改写并扩充以下文章，补充遗漏的内容，提升完整性和可读性，并避免重复。文章：' . $content;
            } else {
              // Japanese
              $improve_prompt = '以下の記事をより網羅的で読みやすい内容に書き直し、不足している情報や視点を追加してください。繰り返しを避けます。記事: ' . $content;
            }
            $body2 = array(
              'model'     => $draft_model,
              'messages'  => array(
                array('role'=>'system','content'=>$sys),
                array('role'=>'user','content'=>$improve_prompt),
              ),
              'max_tokens'  => $draft_max,
              'temperature' => $draft_temp,
            );
            $resp2 = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body2), 'timeout'=>120) );
            if ( ! is_wp_error($resp2) && 200===wp_remote_retrieve_response_code($resp2) ) {
              $j2 = json_decode( wp_remote_retrieve_body($resp2), true );
              $new_content = $j2['choices'][0]['message']['content'] ?? '';
              if ( $new_content ) {
                $content = $new_content;
                $ai_outputs['redraft_'.$attempt_improve] = $new_content;
              }
            }
          } else {
            // 70-89: コーディング/編集による軽微な修正
            $coding_model_key = $pick('coding','model', $default_models['coding']);
            $coding_model     = $model_aliases[ $coding_model_key ] ?? $coding_model_key;
            $coding_temp      = floatval( $pick('coding','temp','0.2') );
            $coding_max       = intval( $pick('coding','max','1500') );
            $coding_prompt = '次の本文を見出しID付与、目次生成前提の整形、不要な重複除去、文法修正を行って返してください。本文のみ：\n\n' . $content;
            $body_code = array(
              'model'     => $coding_model,
              'messages'  => array(
                array('role'=>'system','content'=>$sys),
                array('role'=>'user','content'=>$coding_prompt),
              ),
              'max_tokens'  => $coding_max,
              'temperature' => $coding_temp,
            );
            $resp_code = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body_code), 'timeout'=>120) );
            if ( ! is_wp_error($resp_code) && 200===wp_remote_retrieve_response_code($resp_code) ) {
              $j_code = json_decode( wp_remote_retrieve_body($resp_code), true );
              $edited_code = $j_code['choices'][0]['message']['content'] ?? '';
              if ( $edited_code ) {
                $content = $edited_code;
                $ai_outputs['recoding_'.$attempt_improve] = $edited_code;
              }
            }
          }
          // 採点を再実行
          $scoring_prompt = '次の本文を「網羅性/日本語/SEO/YMYL」の4軸で10点満点採点し、改善TODOを箇条書きで。JSONで返す：{scores:{coverage,ja,seo,ymyl},todo:[...] } 本文: ' . $content;
          $body_re_score = array(
            'model'     => $model,
            'messages'  => array(
              array('role'=>'system','content'=>$sys),
              array('role'=>'user','content'=>$scoring_prompt),
            ),
            'max_tokens'  => $max,
            'temperature' => $temp,
          );
          $resp_score = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body_re_score), 'timeout'=>60) );
          if ( ! is_wp_error($resp_score) && 200===wp_remote_retrieve_response_code($resp_score) ) {
            $j_score = json_decode( wp_remote_retrieve_body($resp_score), true );
            $score_new = $j_score['choices'][0]['message']['content'] ?? '';
            if ( $score_new ) {
              $score = $score_new;
              $ai_outputs['scoring_retry_'.$attempt_improve] = $score_new;
              // 更新された採点結果をポストメタに保存
              if ( $current ) update_post_meta( $current->ID, '_seoone_score_report', $score_new );
            }
            // 再計算
            $numeric_score = 100;
            $score_data2 = json_decode( $score, true );
            if ( is_array($score_data2) && isset($score_data2['scores']) ) {
              $total2 = 0;
              $cnt2 = 0;
              foreach ( $score_data2['scores'] as $val2 ) {
                $total2 += floatval( $val2 );
                $cnt2++;
              }
              if ( $cnt2 > 0 ) {
                $avg2 = $total2 / $cnt2;
                $numeric_score = $avg2 * 10;
              }
            }
          }
          $attempt_improve++;
        }
      }
    }

    // 4) ファクトチェック（factcheck）
    if ( $on('factcheck') && ! empty($api_key) && $content ){
      $model_key = $pick('factcheck','model', $default_models['factcheck']);
      $model = $model_aliases[$model_key] ?? $model_key;
      $temp  = floatval($pick('factcheck','temp','0.0'));
      $max   = intval($pick('factcheck','max','1200'));
      $prompt = '次の本文の数値・固有名詞・主張を検証し、誤りがあれば修正案と出典URLを返す。安全で中立的な表現へリライト提案も含める。本文: ' . $content;
      $body = array(
        'model'=> $model,
        'messages'=>array(
          array('role'=>'system','content'=>$sys),
          array('role'=>'user','content'=>$prompt),
        ),
        'max_tokens'=>$max, 'temperature'=>$temp
      );
      $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>90) );
      if ( ! is_wp_error($resp) && 200===wp_remote_retrieve_response_code($resp) ) {
        $j = json_decode( wp_remote_retrieve_body($resp), true );
        $fc = $j['choices'][0]['message']['content'] ?? '';
        $ai_outputs['factcheck'] = $fc;
      }
    }

    // 5) 要約（summary）
    if ( ! empty($api_key) && $content ){
      $model_key = $pick('draft','model', $default_models['draft']);
      $model = $model_aliases[$model_key] ?? $model_key;
      // 言語ごとに要約プロンプトを変更
      if ( $language === 'en' ) {
        $prompt = 'Summarize the following in 300-500 words and return only the summary: ' . $content;
      } elseif ( $language === 'zh' ) {
        $prompt = '请将以下内容用300到500字进行总结，并只返回总结：' . $content;
      } else {
        $prompt = '以下を300〜500字で要約し、要約のみ返す：' . $content;
      }
      $body = array(
        'model'=> $model,
        'messages'=>array(
          array('role'=>'system','content'=>$sys),
          array('role'=>'user','content'=>$prompt),
        ),
        'max_tokens'=>600, 'temperature'=>0.2
      );
      $resp = wp_remote_post( $endpoint, array('headers'=>$headers, 'body'=> wp_json_encode($body), 'timeout'=>60) );
      if ( ! is_wp_error($resp) && 200===wp_remote_retrieve_response_code($resp) ) {
        $j = json_decode( wp_remote_retrieve_body($resp), true );
        $summary = $j['choices'][0]['message']['content'] ?? '';
        $ai_outputs['summary'] = $summary;
      }
    }

    // --- ファクトチェック後に記事本文の長さをチェックし、目標に満たない場合は追記を生成します ---
    if ( ! empty( $content ) && ! empty( $api_key ) ) {
      // 記事長計算用の関数
      $seoone_compute_length_final = function( $txt ) use ( $language ) {
        $plain = wp_strip_all_tags( $txt );
        if ( $language === 'en' ) {
          return str_word_count( $plain );
        } else {
          return mb_strlen( $plain, 'UTF-8' );
        }
      };
      $current_len_final = $seoone_compute_length_final( $content );
      $attempt_final = 0;
      // 追記生成の試行回数を増やし、記事が途中で切れないようにします。
      // 記事の長さに関わらず、結論・FAQ・CTA が含まれるまで生成を繰り返します。
      $max_attempts_final = 12;
      while ( ! $this->has_complete_article( $content, $language ) && $attempt_final < $max_attempts_final ) {
        // draft モデル設定を取得
        $draft_model_key2 = $pick('draft','model', $default_models['draft'] );
        $draft_model2     = $model_aliases[ $draft_model_key2 ] ?? $draft_model_key2;
        $draft_temp2      = floatval( $pick('draft','temp','0.3') );
        // 追記生成ではより多くのトークンを許容し、長文を生成しやすくする。
        $draft_max2       = intval( $pick('draft','max','8192') );
        // 追記用プロンプト: 結論やFAQ、CTAが未完成であることを知らせ、続きを完結させるように指示
        if ( $language === 'en' ) {
          $cont_prompt2 = 'Continue writing to complete the article. Ensure you add any missing conclusion, FAQ and CTA sections and finish naturally. Avoid repeating previous content and introduce new perspectives and details. Current article: ' . $content;
        } elseif ( $language === 'zh' ) {
          $cont_prompt2 = '继续撰写文章以完善内容。请确保添加缺失的总结、常见问题和 CTA 部分，并自然收尾。不要重复前文，加入新的观点和细节。文章：' . $content;
        } else {
          $cont_prompt2 = '記事の続きを書いて完成させてください。まだ存在しないまとめ、FAQ、CTA セクションを追加し、自然に終わらせてください。前の内容を繰り返さず、新しい視点や詳細を盛り込んでください。本文: ' . $content;
        }
        $body_more = array(
          'model'     => $draft_model2,
          'messages'  => array(
            array( 'role' => 'system', 'content' => $sys ),
            array( 'role' => 'user',   'content' => $cont_prompt2 ),
          ),
          'max_tokens'  => $draft_max2,
          'temperature' => $draft_temp2,
        );
        $resp_more = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body_more ), 'timeout' => 120 ) );
        if ( ! is_wp_error( $resp_more ) && 200 === wp_remote_retrieve_response_code( $resp_more ) ) {
          $j_more = json_decode( wp_remote_retrieve_body( $resp_more ), true );
          $extra_more = $j_more['choices'][0]['message']['content'] ?? '';
          if ( $extra_more ) {
            $content .= "\n\n" . $extra_more;
            $attempt_final++;
            $ai_outputs[ 'final_extension_' . $attempt_final ] = $extra_more;
            $current_len_final = $seoone_compute_length_final( $content );
            continue;
          }
        }
        // break if API fails to return new content
        break;
      }
      // 追記後に要約を再生成します（必要な場合）
      if ( $attempt_final > 0 ) {
        // 再度要約
        $model_key_sum = $pick('draft','model', $default_models['draft'] );
        $model_sum     = $model_aliases[$model_key_sum] ?? $model_key_sum;
        if ( $language === 'en' ) {
          $prompt_sum = 'Summarize the following in 300-500 words and return only the summary: ' . $content;
        } elseif ( $language === 'zh' ) {
          $prompt_sum = '请将以下内容用300到500字进行总结，并只返回总结：' . $content;
        } else {
          $prompt_sum = '以下を300〜500字で要約し、要約のみ返す：' . $content;
        }
        $body_sum = array(
          'model' => $model_sum,
          'messages' => array(
            array( 'role' => 'system', 'content' => $sys ),
            array( 'role' => 'user',   'content' => $prompt_sum ),
          ),
          'max_tokens' => 600,
          'temperature' => 0.2,
        );
        $resp_sum = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body_sum ), 'timeout' => 60 ) );
        if ( ! is_wp_error( $resp_sum ) && 200 === wp_remote_retrieve_response_code( $resp_sum ) ) {
          $j_sum = json_decode( wp_remote_retrieve_body( $resp_sum ), true );
          $summary_new = $j_sum['choices'][0]['message']['content'] ?? '';
          if ( $summary_new ) {
            $summary = $summary_new;
            $ai_outputs['summary_after_extension'] = $summary_new;
          }
        }
      }

      // --- 最終的に記事が完結していなければ簡易的なまとめ・FAQ・CTAを追加 ---
      // has_complete_article() は「まとめ/結論」「FAQ」「句読点で締めくくる」ことを満たすか検査します。
      if ( ! $this->has_complete_article( $content, $language ) ) {
        // Append a conclusion section summarising the main points using the existing summary if available
        if ( $language === 'en' ) {
          $content .= "\n\n<h2>Conclusion</h2><p>In summary, this article has provided key points and guidance on the topic. Please refer back to the sections above for details.</p>";
          $content .= "\n\n<h2>FAQ</h2><details class=\"faq-item\"><summary>What is the most frequently asked question?</summary><p>This is a placeholder FAQ answer. Please revise accordingly.</p></details>";
          $content .= "\n\n<h2>CTA</h2><p>For more information or assistance, please contact us.</p>";
        } elseif ( $language === 'zh' ) {
          $content .= "\n\n<h2>总结</h2><p>本文总结了主题的核心要点和指导。请回顾以上章节以获取详细信息。</p>";
          $content .= "\n\n<h2>常见问题</h2><details class=\"faq-item\"><summary>最常见的问题是什么？</summary><p>这是一个占位符答案，请根据需要进行修改。</p></details>";
          $content .= "\n\n<h2>CTA</h2><p>如需更多信息或帮助，请联系我们。</p>";
        } else {
          // default Japanese fallback
          $content .= "\n\n<h2>まとめ</h2><p>本稿ではテーマに関するポイントとガイドラインを解説しました。詳細は各節を参照してください。</p>";
          $content .= "\n\n<h2>FAQ（よくある質問）</h2><details class=\"faq-item\"><summary>もっともよくある質問は？</summary><p>これはプレースホルダの回答です。適宜修正してください。</p></details>";
          $content .= "\n\n<h2>CTA</h2><p>詳細情報やお問い合わせはお気軽にご連絡ください。</p>";
        }
      }
    }

    // 画像や目次・構造化は他クラスに委譲（ここでは本文保存まで）

    // ---------------------------------------------------------------
    // AIが返した本文の冒頭にタイトルが含まれている場合は抽出します。
    // ユーザからのタイトル指定がない場合や、AIがより適切なタイトルを生成した場合に
    // 記事タイトルとして利用するためです。フォーマットは各言語で以下を想定：
    // 日本語: 「タイトル：......」
    // 中国語: 「标题：......」
    // 英語:   "Title: ..." または "TITLE: ..."
    // 該当する表記で始まっていれば行頭のタイトル行を除去し、$title 変数に格納します。
    $trimmed_content = ltrim( $content );
    // 正規表現でタイトルプレフィックスを検出。
    // Markdown の見出し記号 (#) や半角スペースが先頭に付く場合にも対応します。
    if ( preg_match( '/^\s*#?\s*(タイトル|題名|題目|标题|Title|TITLE)[：:]+\s*(.+?)\r?\n/u', $trimmed_content, $m ) ) {
      $detected_title = trim( $m[2] );
      // 抽出したタイトルが存在する場合は上書き
      if ( ! empty( $detected_title ) ) {
        $title = $detected_title;
      }
      // コンテンツからタイトル行を除去
      $content = ltrim( substr( $trimmed_content, strlen( $m[0] ) ) );
    }

    // --- Pexels 画像挿入 ---
    // 設定で Pexels 使用が有効かつ API キーが存在する場合、画像説明文に基づいてフリー画像を挿入します。
    if ( ! empty( $opt['use_pexels'] ) && ! empty( $opt['pexels_api_key'] ) ) {
      $content = $this->insert_pexels_images( $content, $opt['pexels_api_key'] );
    }

    // --- マークダウン見出しをHTML見出しへ変換 ---
    // AI が Markdown 記法 (##, ###) を使用して見出しを出力した場合に備え、
    // 正規表現で h2/h3 タグへ置換します。これにより目次生成や ID 付与が正しく行われます。
    // 先に h3 を処理してから h2 を処理することで、ネスト構造が保持されます。
    $content = preg_replace('/^###\s*(.+)$/m', '<h3>$1</h3>', $content);
    $content = preg_replace('/^##\s*(.+)$/m', '<h2>$1</h2>', $content);

    // --- 見出しID付与と目次生成 ---
    $content = $this->generate_toc_and_ids( $content, $language );

    // --- 画像の正規化と位置調整 ---
    // 目次生成後に全ての <img> を走査し、外部画像をダウンロードしてメディアに登録した上で
    // <picture> タグに置き換えます。Pexels 検索やプレースホルダ生成もこの中で行われます。
    $content = $this->normalize_images_to_media( $content, $opt, 0 );
    // 最初の画像を要約の直後、目次の前に移動します。
    $content = $this->reposition_first_image_tag( $content );

    // --- スタイル注入 ---
    // Answer Card、FAQ、参考文献などの装飾用に簡易CSSを挿入します。既に挿入されていない場合のみ追加します。
    $style_snippet = '<style>
    .seoone-toc summary { font-weight:bold; cursor:pointer; }
    .seoone-toc ul { list-style: none; padding-left: 1em; }
    .seoone-toc li { margin: 0.3em 0; }
    .answer-card { background:#f7f7fb; border-left:4px solid #0073aa; padding:10px 15px; margin:1em 0; border-radius:4px; }
    .answer-card ul { list-style: disc inside; margin:0; padding-left:1em; }
    .faq-item { margin:0.5em 0; }
    .faq-item summary { font-weight:bold; cursor:pointer; }
    .faq-item p { margin:0.3em 0 0.6em; }
    .references { background:#f9f9f9; border-top:1px solid #ddd; padding:10px 15px; margin-top:2em; }
    .references ul { list-style:none; padding-left:0; }
    .references li { margin:0.3em 0; }
    .references li a { text-decoration: underline; color:#0073aa; }
    </style>';
    if ( strpos( $content, '<style>' ) === false ) {
      $content = $style_snippet . "\n" . $content;
    }

    // 投稿ステータスと日時
    $post_status = 'draft';
    $post_date   = null;
    if ( ! empty( $schedule ) ) {
      $ts = strtotime( $schedule );
      if ( $ts ) {
        $post_status = 'future';
        $post_date   = gmdate( 'Y-m-d H:i:s', $ts );
      }
    }
    // --- タイトルがデフォルトの場合は最初の見出しから補完 ---
    if ( empty( $title ) || preg_match( '/に関する記事$/u', $title ) ) {
      if ( preg_match( '/<h2[^>]*>(.*?)<\/h2>/is', $content, $mtitle ) ) {
        $candidate = wp_strip_all_tags( $mtitle[1] );
        if ( ! empty( $candidate ) ) {
          $title = $candidate;
        }
      }
    }

    $post_args = array(
      'post_title'   => $title,
      'post_content' => $content ?: '（AI生成に失敗しました）',
      'post_status'  => $post_status,
      'post_type'    => 'post',
    );
    if ( $post_date ) {
      $post_args['post_date']     = $post_date;
      $post_args['post_date_gmt'] = get_gmt_from_date( $post_date );
    }
    $post_id = wp_insert_post( $post_args, true );
    if ( is_wp_error($post_id) ) return $post_id;

    // --- 画像の正規化（投稿に添付後） ---
    // 外部画像や相対パスを含む <img> タグを全てメディア登録済みの <picture> に置き換え、
    // 最初の画像を要約直後に移動します。これにより、本文に裸のURLが残らず、メディアライブラリへの登録も保証されます。
    $content = $this->normalize_images_to_media( $content, $opt, $post_id );
    $content = $this->reposition_first_image_tag( $content );
    // 更新された本文を投稿に反映させます。
    wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );

    // 要約と読了時間を改めて計算します。先ほどの変数 $content は正規化済みなのでこちらを使用します。
    $sum = $summary ?: mb_substr( wp_strip_all_tags($content), 0, 300, 'UTF-8' );
    update_post_meta($post_id, '_seoone_summary', $sum);
    $wc = mb_strlen( wp_strip_all_tags($content), 'UTF-8' );
    $minutes = (int)ceil( $wc / 500 );
    update_post_meta($post_id, '_seoone_readtime', $minutes);
    update_post_meta($post_id, '_seoone_wordcount', $wc);

    // AI出力ログ
    update_post_meta($post_id, '_seoone_ai_outputs', $ai_outputs);

    return get_post($post_id);
  }

  /**
   * 競合分析ヘルパー
   *
   * 指定したキーワードに基づいて上位コンテンツの見出しやサブトピックを洗い出し、
   * 追加すべき視点や不足している内容を提案します。SeoOne_Competitor の内部実装を
   * 参照せずに、同様の機能をこのクラスから呼び出せるようにしました。
   *
   * @param string $keywords キーワードまたはトピック
   * @param string $language 記事生成対象の言語コード
   * @param array $opt プラグイン設定オプション
   * @return string 箇条書きの分析結果テキスト。失敗時は空文字列。
   */
  private function run_competitor_analysis( $keywords, $language, $opt ) {
    $api_key = $opt['ai_api_key'] ?? '';
    // モデル選択: 競合分析には情報取得と同じモデルを使用する。既定は Perplexity。
    $model_key = $opt['ai_model_retrieval'] ?? ( $opt['ai_model'] ?? 'perplexity' );
    // モデル名のエイリアスをOpenRouter用IDへ変換
    $model_aliases = array(
      'gpt-4o'             => 'openai/gpt-4o',
      'gpt-4'              => 'openai/gpt-4',
      'gpt-3.5-turbo'      => 'openai/gpt-3.5-turbo',
      'claude-sonnet-4.5'  => 'anthropic/claude-sonnet-4.5',
      'claude-opus-4.1'    => 'anthropic/claude-opus-4.1',
      'claude-3-sonnet'    => 'anthropic/claude-3-sonnet-20240229',
      'claude-3-opus'      => 'anthropic/claude-opus-4.1',
      'perplexity'         => 'perplexity/sonar',
      'perplexity-sonar'   => 'perplexity/sonar',
      'gemini'             => 'google/gemini-pro',
      'gemini-2.5-flash'   => 'google/gemini-1.5-flash',
      'gpt-5-pro'          => 'openai/gpt-5-pro',
      'gpt-5-image'        => 'openai/gpt-5-image',
    );
    $model   = $model_aliases[ $model_key ] ?? $model_key;
    if ( empty( $api_key ) ) {
      return '';
    }
    $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    $headers  = array(
      'Authorization' => 'Bearer ' . $api_key,
      'Content-Type'  => 'application/json',
    );
    // 言語ごとにプロンプトを作成
    if ( $language === 'en' ) {
      $prompt = 'You are an SEO content strategist. For the keyword or topic "' . $keywords . '", analyze the top ranking articles and identify typical headings and subtopics covered. Suggest additional angles or gaps that could be addressed to create a more comprehensive and unique article. Provide your output in English using bullet points.';
    } elseif ( $language === 'zh' ) {
      $prompt = '你是一名SEO内容策划师。对于关键词或主题 "' . $keywords . '"，分析搜索排名前列的文章通常涵盖的主要标题和子主题。然后提出额外的角度或内容缺口，使文章更加全面且独特。请用中文列出要点。';
    } else {
      // Japanese default
      $prompt = 'あなたはSEOコンテンツ戦略家です。キーワードまたはトピック「' . $keywords . '」について、検索上位の記事が取り上げている一般的な見出しやサブトピックを分析し、より網羅的でユニークな記事にするための追加の視点や不足している点を提案してください。箇条書きで日本語で出力してください。';
    }
    $body = array(
      'model' => $model,
      'messages' => array(
        array( 'role' => 'system', 'content' => 'You are an expert SEO assistant.' ),
        array( 'role' => 'user', 'content' => $prompt ),
      ),
      'max_tokens'   => 1024,
      'temperature'  => 0.5,
    );
    $resp = wp_remote_post( $endpoint, array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 60 ) );
    if ( is_wp_error( $resp ) ) {
      return '';
    }
    if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
      return '';
    }
    $j = json_decode( wp_remote_retrieve_body( $resp ), true );
    $text = $j['choices'][0]['message']['content'] ?? '';
    return $text;
  }

  /**
   * 記事本文中の画像説明文を検出し、Pexels API を使用してフリー画像を挿入します。
   *
   * 画像説明の書式は、AIの出力指示に合わせて次のパターンに一致します：
   *   （画像説明：○○○）
   *   （画像説明: ○○○）
   *   （画像説明： ○○○）
   * 複数言語に対応するため、中国語の「图片说明」、英語の「Image description」にも対応します。
   * Pexels API キーが無効の場合は画像を挿入せず、元の説明文を残します。
   *
   * @param string $content AI生成後の本文
   * @param string $api_key Pexels API キー
   * @return string 画像タグを挿入した本文
   */
  private function insert_pexels_images( $content, $api_key ) {
    // 正規表現で画像説明のパターンを検出
    // 画像説明は丸括弧（）、角括弧【】や[]で記されることがあるため、開始・終了括弧を柔軟に許容します。
    // Allow the colon after the description keyword to be optional (some AI outputs omit it)
    $pattern = '/[（\[【]\s*(?:画像説明|图片说明|Image\s*description)\s*[:：]?\s*(.+?)\s*[】\]）]/u';
    $callback = function( $matches ) use ( $api_key ) {
      $desc = trim( $matches[1] );
      if ( $desc === '' ) {
        return $matches[0];
      }
      // Pexels API で検索
      $url = 'https://api.pexels.com/v1/search?query=' . rawurlencode( $desc ) . '&per_page=1';
      $resp = wp_remote_get( $url, array( 'headers' => array( 'Authorization' => $api_key ), 'timeout' => 20 ) );
      if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( is_array( $data ) && ! empty( $data['photos'] ) ) {
          $photo = $data['photos'][0];
          // 画像URLを選択。availableサイズに応じて large2x, large, medium 等。
          $img_url = '';
          if ( isset( $photo['src']['large2x'] ) ) {
            $img_url = $photo['src']['large2x'];
          } elseif ( isset( $photo['src']['large'] ) ) {
            $img_url = $photo['src']['large'];
          } elseif ( isset( $photo['src']['medium'] ) ) {
            $img_url = $photo['src']['medium'];
          }
          if ( $img_url ) {
            // Download the image and register it in the media library
            $attachment_id = \SeoOne_Images::download_and_register_image( $img_url, $desc );
            if ( $attachment_id ) {
              // Build a <picture> or <figure> tag using the attachment
              $html = \SeoOne_Images::get_picture_html( $attachment_id, $desc );
              // Fallback: if get_picture_html returns empty, produce simple figure
              if ( empty( $html ) ) {
                $html  = '<figure class="seoone-image">';
                $html .= '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $desc ) . '" loading="lazy">';
                $html .= '<figcaption>' . esc_html( $desc ) . '</figcaption>';
                $html .= '</figure>';
              }
              return $html;
            } else {
              // fallback: still display image without registering
              $html  = '<figure class="seoone-image">';
              $html .= '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $desc ) . '" loading="lazy">';
              $html .= '<figcaption>' . esc_html( $desc ) . '</figcaption>';
              $html .= '</figure>';
              return $html;
            }
          }
        }
      }
      // API が失敗した場合は、説明文を用いて簡易的な代替画像（SVGプレースホルダ）を生成します。
      $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"><rect width="100%" height="100%" fill="#f0f0f0"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="24" fill="#555">' . htmlspecialchars( $desc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</text></svg>';
      $encoded = base64_encode( $svg );
      $html  = '<figure class="seoone-image">';
      $html .= '<img src="data:image/svg+xml;base64,' . $encoded . '" alt="' . esc_attr( $desc ) . '" loading="lazy">';
      $html .= '<figcaption>' . esc_html( $desc ) . '</figcaption>';
      $html .= '</figure>';
      return $html;
    };
    // preg_replace_callback により本文中のパターンを置換
    $new_content = preg_replace_callback( $pattern, $callback, $content );
    return $new_content;
  }

  /**
   * 既存の投稿タイトルをキーワードに基づいて取得します。
   * 同じキーワードを含む記事が存在する場合、そのタイトルを取得して
   * AI に重複しないよう指示するために使用します。
   *
   * @param string $keywords コンマ区切りのキーワード
   * @param int    $limit    取得するタイトル数の上限（デフォルト3件）
   * @return array 既存投稿タイトルの配列
   */
  private function get_existing_titles_by_keywords( $keywords, $limit = 3 ) {
    $titles = array();
    if ( empty( $keywords ) ) {
      return $titles;
    }
    // キーワードを分割して検索文字列を作成
    $search_terms = preg_split( '/\s*,\s*/', $keywords );
    $search_terms = array_filter( $search_terms, function( $s ) { return $s !== ''; } );
    // 検索クエリを組み立て
    $search_query = implode( ' ', $search_terms );
    // WP_Query で公開済み投稿を検索
    $args = array(
      'post_type'      => array( 'post', 'page', 'seoone' ),
      'post_status'    => 'publish',
      's'              => $search_query,
      'posts_per_page' => $limit,
      'fields'         => 'ids',
    );
    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
      foreach ( $query->posts as $pid ) {
        $titles[] = get_the_title( $pid );
      }
    }
    wp_reset_postdata();
    return $titles;
  }

  /**
   * Perform a one-shot Pexels image search for the given text and return a picture tag.
   * The downloaded image is saved to the media library and then converted into
   * a <picture> tag via SeoOne_Images. If the search fails, returns an empty string.
   *
   * @param string $text   Search query and alt text
   * @param string $api_key Pexels API key
   * @param int    $post_id Post ID to attach the image to
   * @return string HTML <picture> tag or empty string
   */
  private function pexels_by_text( $text, $api_key, $post_id ) {
    $u = 'https://api.pexels.com/v1/search?query=' . rawurlencode( $text ) . '&per_page=1';
    $r = wp_remote_get( $u, array( 'headers' => array( 'Authorization' => $api_key ), 'timeout' => 20 ) );
    if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) {
      return '';
    }
    $d = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( ! is_array( $d ) || empty( $d['photos'][0]['src'] ) ) {
      return '';
    }
    $srcs = $d['photos'][0]['src'];
    $img_url = $srcs['large2x'] ?? ( $srcs['large'] ?? ( $srcs['medium'] ?? '' ) );
    if ( ! $img_url ) {
      return '';
    }
    $id = \SeoOne_Images::download_and_register_image( $img_url, $text );
    if ( ! $id ) {
      return '';
    }
    $tag = \SeoOne_Images::get_picture_html( $id, $text );
    return $tag;
  }

  /**
   * Normalize all <img> elements in the content to registered media attachments.
   * - External URLs are downloaded and saved to the media library via SeoOne_Images.
   * - Relative filenames or plain filenames are searched on Pexels using the alt text or filename.
   * - If neither succeeds, a simple SVG placeholder is generated.
   * After normalization, all images become <picture> tags and can be repositioned.
   *
   * @param string $content HTML content
   * @param array  $opt     Plugin options (must include use_pexels and pexels_api_key keys)
   * @param int    $post_id The post ID to attach downloaded images to
   * @return string Normalized content
   */
  private function normalize_images_to_media( $content, $opt, $post_id ) {
    if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER ) ) {
      foreach ( $matches as $one ) {
        $src = $one[1];
        $alt = trim( wp_strip_all_tags( $one[2] ) );
        $orig = $one[0];
        $pic = '';
        // absolute URL
        if ( preg_match( '#^https?://#i', $src ) ) {
          $id = \SeoOne_Images::download_and_register_image( $src, $alt, $post_id );
          if ( $id ) {
            $pic = \SeoOne_Images::get_picture_html( $id, $alt );
          }
        } else {
          // Use Pexels for filenames/relative paths if enabled
          if ( ! empty( $opt['use_pexels'] ) && ! empty( $opt['pexels_api_key'] ) ) {
            $query = $alt ?: basename( $src );
            $pic = $this->pexels_by_text( $query, $opt['pexels_api_key'], $post_id );
          }
          if ( empty( $pic ) ) {
            $pic = \SeoOne_Images::generate_placeholder_svg( $alt ?: 'image' );
          }
        }
        if ( $pic ) {
          $content = str_replace( $orig, $pic, $content );
        }
      }
    }
    return $content;
  }

  /**
   * Reposition the first inserted image so that it appears after the summary and before the TOC.
   *
   * This function searches for the first <figure class="seoone-image"> or <picture class="seoone-image">
   * block in the content, removes it from its original position, and reinserts it immediately before
   * the <details class="seoone-toc"> block. If no TOC is found or no image exists, the content
   * remains unchanged.
   *
   * @param string $content HTML content
   * @return string Modified content with the first image repositioned
   */
  private function reposition_first_image_tag( $content ) {
    // Find the first figure or picture with class seoone-image
    $img_html = '';
    if ( preg_match( '/<figure\s+class="seoone-image"[^>]*>.*?<\/figure>/s', $content, $m ) ) {
      $img_html = $m[0];
    } elseif ( preg_match( '/<picture\s+class="seoone-image"[^>]*>.*?<\/picture>/s', $content, $m ) ) {
      $img_html = $m[0];
    }
    if ( ! empty( $img_html ) ) {
      // Remove the image from its original location (only first occurrence)
      $content = preg_replace( '/' . preg_quote( $img_html, '/' ) . '/', '', $content, 1 );
      // Find the TOC block
      if ( preg_match( '/<details\s+class="seoone-toc"[^>]*>/s', $content, $toc_match ) ) {
        $toc_tag = $toc_match[0];
        // Insert the image before the TOC tag
        $content = str_replace( $toc_tag, $img_html . "\n" . $toc_tag, $content );
      } else {
        // If no TOC, prepend the image to the content
        $content = $img_html . "\n" . $content;
      }
    }
    return $content;
  }

  /**
   * Determine whether the article appears to be complete.
   * Checks for the presence of a conclusion or summary heading (まとめ, 結論, まとめとCTA, Conclusion)
   * and ensures the article ends with a full sentence (punctuation).
   *
   * @param string $content HTML content
   * @param string $language Language code
   * @return bool True if article appears complete, false otherwise
   */
  private function has_complete_article( $content, $language ) {
    // Strip tags and decode HTML entities for plain-text evaluation
    $plain = wp_strip_all_tags( $content );
    // Check for presence of conclusion headings (e.g. まとめ, 結論, Conclusion)
    $has_conclusion = false;
    $conclusion_keywords = array( 'まとめ', '結論', 'まとめとCTA', 'Conclusion', '总结' );
    foreach ( $conclusion_keywords as $kw ) {
      if ( mb_stripos( $plain, $kw, 0, 'UTF-8' ) !== false ) {
        $has_conclusion = true;
        break;
      }
    }
    // Check for presence of FAQ/よくある質問 section
    $has_faq = false;
    $faq_keywords = array( 'FAQ', 'よくある質問', '常见问题' );
    foreach ( $faq_keywords as $kw ) {
      if ( mb_stripos( $plain, $kw, 0, 'UTF-8' ) !== false ) {
        $has_faq = true;
        break;
      }
    }
    // Check that the content ends with a sentence-ending punctuation mark
    $last_char = mb_substr( $plain, -1, 1, 'UTF-8' );
    $end_punctuation = array( '。', '．', '！', '!', '?', '？', '.', ';' );
    $ends_ok = in_array( $last_char, $end_punctuation, true );
    // Consider article complete only if it contains both a conclusion and FAQ section and ends cleanly
    return ( $has_conclusion && $has_faq && $ends_ok );
  }

  /**
   * 記事本文中の <h2> と <h3> 見出しに id 属性を連番で付与し、H2 見出しから目次を生成します。
   * 目次は折りたたみ可能な <details> ブロックとして本文の先頭に挿入されます。
   *
   * @param string $content 記事本文
   * @param string $language 言語コード（現在未使用）
   * @return string ID付与と目次挿入後の本文
   */
  private function generate_toc_and_ids( $content, $language ) {
    $h2_count = 0;
    $toc      = array();
    // H2 ID付与とTOC情報収集
    $content = preg_replace_callback( '/<h2([^>]*)>(.*?)<\/h2>/is', function( $m ) use ( &$h2_count, &$toc ) {
      $h2_count++;
      $attrs = $m[1];
      $inner = $m[2];
      // 抽出したタイトルからHTMLタグを除去
      $title_clean = wp_strip_all_tags( $inner );
      $id = 'h2-' . $h2_count;
      $toc[] = array( 'id' => $id, 'title' => $title_clean );
      // id が既に存在するかどうかをチェック
      if ( preg_match( '/id\s*=\s*\"([^"]+)\"/i', $attrs ) ) {
        // 既存の id を保持
        return '<h2' . $attrs . '>' . $inner . '</h2>';
      } else {
        return '<h2 id="' . $id . '"' . $attrs . '>' . $inner . '</h2>';
      }
    }, $content );
    // H3 ID付与: 各H2ごとにカウンタをリセット
    $h3_count = 0;
    $current_h2 = 0;
    $content = preg_replace_callback( '/<h3([^>]*)>(.*?)<\/h3>/is', function( $m ) use ( &$h2_count, &$h3_count, &$current_h2 ) {
      // id は h3-<h2番号>-<通番>
      // h2_count は最後に処理されたH2の数を示すわけではないので、ここでは単純にインクリメント
      $h3_count++;
      $id = 'h3-' . $h3_count;
      $attrs = $m[1];
      $inner = $m[2];
      if ( preg_match( '/id\s*=\s*\"([^"]+)\"/i', $attrs ) ) {
        return '<h3' . $attrs . '>' . $inner . '</h3>';
      } else {
        return '<h3 id="' . $id . '"' . $attrs . '>' . $inner . '</h3>';
      }
    }, $content );
    // TOC HTML を生成
    if ( ! empty( $toc ) ) {
      $toc_html = '<details class="seoone-toc"><summary>目次</summary><ul>';
      foreach ( $toc as $item ) {
        $toc_html .= '<li><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['title'] ) . '</a></li>';
      }
      $toc_html .= '</ul></details>';
      // 既に本文内に目次がある場合は追加しない
      if ( strpos( $content, 'seoone-toc' ) === false ) {
        return $toc_html . "\n" . $content;
      }
    }
    return $content;
  }
}
