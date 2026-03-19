<?php
function vogo_register_custom_post_types() {
    register_post_type('question', [
        'labels' => [
            'name' => 'Questions',
            'singular_name' => 'Question',
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title', 'editor', 'author'],
        'show_in_rest' => true,
    ]);

    register_post_type('reply', [
        'labels' => [
            'name' => 'Replies',
            'singular_name' => 'Reply',
        ],
        'public' => false, // only accessible programmatically
        'supports' => ['editor', 'author'],
        'show_in_rest' => true,
        'show_ui' => true,
        'show_in_menu' => true,
    ]);
}
add_action('init', 'vogo_register_custom_post_types');

function vogo_add_question_meta_boxes() {
    add_meta_box('vogo_question_meta', 'Question Details', 'vogo_question_meta_callback', 'question');
}
add_action('add_meta_boxes', 'vogo_add_question_meta_boxes');

function vogo_question_meta_callback($post) {
    $city = get_post_meta($post->ID, 'city', true);
    $interest = get_post_meta($post->ID, 'interest', true);
    ?>
    <label>City:</label><br>
    <input type="text" name="city" value="<?php echo esc_attr($city); ?>"><br><br>
    <label>Interest:</label><br>
    <input type="text" name="interest" value="<?php echo esc_attr($interest); ?>">
    <?php
}

function vogo_save_question_meta($post_id) {
    if (isset($_POST['city'])) {
        update_post_meta($post_id, 'city', sanitize_text_field($_POST['city']));
    }
    if (isset($_POST['interest'])) {
        update_post_meta($post_id, 'interest', sanitize_text_field($_POST['interest']));
    }
}
add_action('save_post_question', 'vogo_save_question_meta');

add_action('add_meta_boxes', function () {
    add_meta_box('reply_meta', 'Reply Settings', function ($post) {
        $visibility = get_post_meta($post->ID, 'visibility', true);
        ?>
        <label>Visibility:</label>
        <select name="visibility">
            <option value="public" <?php selected($visibility, 'public'); ?>>Public</option>
            <option value="private" <?php selected($visibility, 'private'); ?>>Private</option>
        </select>
        <?php
    }, 'reply');
});

add_action('save_post_reply', function ($post_id) {
    if (isset($_POST['visibility'])) {
        update_post_meta($post_id, 'visibility', sanitize_text_field($_POST['visibility']));
    }
});

/**
 * Render a single reply and its children recursively.
 * This function is globally available for both the shortcode and AJAX handler.
 */
function vogo_render_single_reply($reply, $grouped_replies, $current_user, $question) {
    ob_start();
    // Output styles only once per page load (if needed, can be optimized)
    static $rendered_css = false;
    if (!$rendered_css) {
        ?>
        <style>
.vogo-reply-block {
    display: flex;
    flex-direction: column;
    margin-bottom: 8px;
    font-family: system-ui, sans-serif;
    /* Remove any border or background for minimal look */
}

.vogo-reply-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    margin-bottom: 2px;
}

.vogo-reply-avatar img {
    border-radius: 50%;
}

.vogo-reply-meta-info {
    display: flex;
    /* flex-direction: column; */
    gap: 3px;
}

.vogo-reply-inline-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}

.vogo-author-name {
    font-weight: 600;
    font-size: 14px;
}

.vogo-time-stamp,
.vogo-visibility-label {
    font-size: 12px;
    color: #888;
}

.vogo-reply-inner {
    padding: 0;
    font-size: 14px;
    max-width: 90%;
    background: none;
    border: none;
}

.vogo-reply-content-img img {
    max-width: 100%;
    border-radius: 6px;
    margin-top: 4px;
}

.vogo-reply-toggle,
.vogo-reply-login {
    font-size: 13px;
    color: #0073b1;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    text-decoration: underline;
    margin-top: 4px;
}
.vogo-child-comment-list {
    list-style: none;
    padding-left: 0;
    margin-top: 8px;
    font-size: 13px;
    color: #333;
    border-top: 1px solid #ddd;
}
.vogo-child-comment {
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}
.vogo-child-comment-text {
    display: block;
    line-height: 1.5;
}
.vogo-child-comment-time {
    color: #888;
    font-size: 12px;
    margin-left: 6px;
}
.vogo-comment-author {
    color: #0073b1;
}
.vogo-toggle-child-comments {
    background: none;
    border: none;
    color: #1d72b8;
    font-size: 13px;
    padding: 0;
    cursor: pointer;
    margin-bottom: 6px;
    
}
.vogo-reply-action-row {
    flex-direction: row-reverse;
}
</style>
        <style>

		.vogo-main-reply-form input[type="submit"] {
			background: transparent !important;
		}

			.vogo-reply-form.vogo-main-reply-form.reply-bound {
			position: relative !important;
		}

		

		.vogo-main-reply-form-options .vogo-file-label {
			background: transparent !important;
		}

		

			.vogo-main-reply-form.reply-bound .vogo-main-reply-form-options{
				margin-top: -58px ;
			}
				
					@media only screen and (max-width: 767px) {
			.vogo-main-reply-form {
				display: flex !important;
		
			}
		}
			
				/* Style for nested reply form */
        .vogo-nested-reply-form {
            margin-top: 16px;
            padding: 10px 0;
        }
        .vogo-nested-reply-form textarea {
            background: #fefefe;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            font-size: 14px;
            width: 100%;
            resize: vertical;
            font-family: inherit;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .vogo-nested-reply-form textarea:focus {
            border-color: #0073b1;
            box-shadow: 0 0 0 2px rgba(0, 115, 177, 0.2);
            outline: none;
        }
        .vogo-nested-submit {
            padding: 4px 10px;
            font-size: 13px;
            margin-top: 6px;
            /* background: #1d72b8; */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
</style>
        <?php
        $rendered_css = true;
    }
    $visibility = get_post_meta($reply->ID, 'visibility', true);
    $current_user_id = (int) get_current_user_id();
    $author_id = (int) $reply->post_author;
    $question_author_id = (int) $question->post_author;
    // Visibility check: Only show private replies to the author or question author
    if ($visibility === 'private' && $current_user_id !== $author_id && $current_user_id !== $question_author_id) {
        // Instead of outputting the message here, output a placeholder for later grouping
        return '<div class="vogo-private-reply-placeholder" style="display:none;"></div>';
    }
    $author_name = get_the_author_meta('display_name', $author_id);
	    $first_letter = strtoupper(mb_substr($author_name, 0, 1));
    $time_diff = 'acum ' . human_time_diff(strtotime($reply->post_date), current_time('timestamp'));
    $content = trim($reply->post_content);
    // Translate visibility for badge
    $visibility_label = '';
    if ($visibility === 'public') {
        $visibility_label = 'Public';
    } elseif ($visibility === 'private') {
        $visibility_label = 'Privat';
    } else {
        $visibility_label = ucfirst($visibility);
    }
    ?>
    <div class="vogo-reply-block">
        <div class="vogo-reply-body">
            <div class="vogo-reply-meta" style="align-items: start;">
<!--                 <div class="vogo-reply-avatar" style="width: 44px;"><?php //echo get_avatar($author_id, 40); ?></div> -->
                <?php
                $avatar_url = get_user_meta($author_id, 'custom_avatar', true);
                if ($avatar_url) {
                    echo '<div class="vogo-reply-avatar" style="width: 44px;"><img src="' . esc_url($avatar_url) . '" style="width:44px; height:44px; border-radius:100px; object-fit:cover;"></div>';
                } else {
                    echo '<div class="vogo-reply-avatar vogo-initial-avatar" style="width: 44px; height: 44px; background: #337ab7; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 22px; font-weight: bold;">' . esc_html($first_letter) . '</div>';
                }
                ?>
                <div class="vogo-reply-meta-info vogo-reply-inline-meta" style="display:block; width: 88%;">
                    <strong class="vogo-author-name"><?php echo esc_html($author_name); ?></strong>
<!--                     <span class="vogo-time-stamp">• <?php //echo esc_html($time_diff); ?></span> -->
					                    <span class="vogo-time-stamp">• <?php echo esc_html(date('H:i', strtotime($reply->post_date))); ?></span>

<!--                     <span class="vogo-visibility-label">| <?php// echo $visibility_label; ?></span> -->
					<div class="vogo-reply-inner" style="margin-top: -5px;">
                <div class="vogo-reply-content vogo-reply-content-img">
                    <?php echo $content ? wpautop($content) : '<em style="color:gray;">(No content)</em>'; ?>
                </div>
                
                <div class="vogo-reply-form-container" id="reply-form-<?php echo $reply->ID; ?>">
                    <form method="post" enctype="multipart/form-data" class="vogo-reply-form vogo-nested-reply-form">
                        <textarea class="vogo-editor-simple" name="reply_content" rows="3" placeholder="Răspunde la acest mesaj..."></textarea>
                        <div class="vogo-main-reply-form-options">
                            <!-- Removed image upload for replies to replies -->
                            <div class="vogo-main-reply-form-options-inner">
                                <label><input type="radio" name="reply_visibility" value="public" checked> Public</label>
                                <label><input type="radio" name="reply_visibility" value="private"> Privat</label>
                            </div>
                            <input type="submit" value="Răspunde" class="vogo-nested-submit">
                        </div>
                        <input type="hidden" name="parent_reply_id" value="<?php echo $reply->ID; ?>">
                        <input type="hidden" name="vogo_question_id" value="<?php echo esc_attr($question->ID); ?>">
                        <input type="hidden" name="action" value="vogo_submit_reply">
                    </form>
                </div>
                <?php if (!empty($grouped_replies[$reply->ID])): ?>
                    <ul class="vogo-child-comment-list" id="child-comments-<?php echo $reply->ID; ?>" style="display: none;">
                        <?php foreach ($grouped_replies[$reply->ID] as $child_reply): ?>
                            <?php
                                $author_id = (int) $child_reply->post_author;
                                $author_name = get_the_author_meta('display_name', $author_id);
                                $time_diff = 'acum ' . human_time_diff(strtotime($child_reply->post_date), current_time('timestamp'));
                                $content = trim(strip_tags($child_reply->post_content));
                            ?>
                            <li class="vogo-child-comment">
                                <span class="vogo-child-comment-text">
                                    <?php echo esc_html($content); ?> —
                                    <strong class="vogo-comment-author"><?php echo esc_html($author_name); ?></strong>
                                    <span class="vogo-child-comment-time"><?php echo esc_html($time_diff); ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
                </div>
            </div>
            <div class="vogo-reply-action-row" style="display: flex; align-items: start; margin-top: 8px;">
                    <?php if (is_user_logged_in()): ?>
                        <button class="vogo-reply-toggle vogo-reply-button " data-reply-id="<?php echo $reply->ID; ?>">R</button>
                    <?php else: ?>
                        <a href="/login" class="vogo-reply-login">Răspunde</a>
                    <?php endif; ?>

                    <?php if (!empty($grouped_replies[$reply->ID])): ?>
                        <button class="vogo-toggle-child-comments vogo-reply-button vogo-child-comments-btn" data-target="child-comments-<?php echo $reply->ID; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="14" height="14" style="margin-right: 6px;"><path d="M512 240c0 114.9-114.6 208-256 208c-37.1 0-72.3-6.4-104.1-17.9c-11.9 8.7-31.3 20.6-54.3 30.6C73.6 471.1 44.7 480 16 480c-6.5 0-12.3-3.9-14.8-9.9c-2.5-6-1.1-12.8 3.4-17.4l.3-.3c.3-.3 .7-.7 1.3-1.4c1.1-1.2 2.8-3.1 4.9-5.7c4.1-5 9.6-12.4 15.2-21.6c10-16.6 19.5-38.4 21.4-62.9C17.7 326.8 0 285.1 0 240C0 125.1 114.6 32 256 32s256 93.1 256 208z"/></svg>
                            (<?php echo count($grouped_replies[$reply->ID]); ?>)
                        </button>
                    <?php endif; ?>
                </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('vogo_question_thread', 'vogo_render_question_thread');
function vogo_render_question_thread($atts) {
    ob_start();

    $city_filter = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
    $interest_filter = isset($_GET['interest']) ? sanitize_text_field($_GET['interest']) : '';

    $atts = shortcode_atts(['id' => 0], $atts);
    $question_id = 0;
    if (isset($atts['id']) && intval($atts['id']) > 0) {
        $question_id = intval($atts['id']);
    } elseif (isset($_GET['question_id']) && intval($_GET['question_id']) > 0) {
        $question_id = intval($_GET['question_id']);
    } else {
        $question_id = get_the_ID();
    }
    $question = get_post($question_id);
    if (!$question || $question->post_type !== 'question') {
        return '<p>ID întrebare invalid.</p>';
    }

    $city = get_post_meta($question_id, 'city', true);
    $interest = get_post_meta($question_id, 'interest', true);
    $current_user = get_current_user_id();

    // Get replies
    $replies = get_posts([
        'post_type' => 'reply',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'question_id',
                'value' => $question_id,
                'compare' => '='
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    // Group replies by parent_reply_id
    $grouped_replies = [];
    foreach ($replies as $reply) {
        $parent_id = intval(get_post_meta($reply->ID, 'parent_reply_id', true));
        $grouped_replies[$parent_id][] = $reply;
    }

    ?>
   
    <div class="vogo-question-thread">
        <div class="back-to-question-container">
            <?php
              $ref = wp_get_referer();
              if (strpos($ref, 'my-forum-request') !== false) {
                  $back_url = '/my-forum-request?city=' . urlencode($city_filter) . '&interest=' . urlencode($interest_filter);
              } elseif (strpos($ref, 'my-forum-contribution') !== false) {
                  $back_url = '/my-forum-contribution?filter_city=' . urlencode($city_filter) . '&filter_interest=' . urlencode($interest_filter);
              } else {
                  $back_url = '/forum?city=' . urlencode($city_filter) . '&interest=' . urlencode($interest_filter);
              }
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="back-to-question" style="border-radius: 10px; line-height: 0;">
                <?xml version="1.0" ?><svg height="25" viewBox="0 0 48 48" width="25" fill="#000" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h48v48h-48z" fill="none"/><path d="M40 22h-24.34l11.17-11.17-2.83-2.83-16 16 16 16 2.83-2.83-11.17-11.17h24.34v-4z"/></svg>
            </a>
			<div class="vogo-question-header">
<!--             <p class="vogo-question-meta">
                <span class="question-interest"><?php// echo esc_html($interest); ?></span>
                <span class="question-city"><?php// echo esc_html($city); ?></span>
            </p> -->
				<p class="vogo-header-question-author"><?php echo esc_html(get_the_author_meta('display_name', $question->post_author)); ?></p> |
            <h2 class="vogo-question-title"><?php echo esc_html($question->post_title); ?></h2>
				
            <div class="vogo-question-description"><?php echo wpautop($question->post_content); ?></div> 
            <?php if (has_post_thumbnail($question->ID)) : ?>
                <div class="vogo-question-image">
                    <?php echo get_the_post_thumbnail($question->ID, 'large', ['style' => 'max-width:100%; margin-top:15px; border-radius:4px;']); ?>
                </div> 
            <?php endif; ?>
        </div>
        </div>
        

        <div class="vogo-replies-wrapper">
            <div id="vogo-replies-list">
        <?php
            // Render replies recursively using global function
            // Helper to render all top-level replies
            if (!function_exists('vogo_render_replies')) {
                function vogo_render_replies($replies, $grouped_replies, $current_user, $question) {
                    $private_hidden = 0;
                    $output = '';
                    foreach ($replies as $reply) {
                        $html = vogo_render_single_reply($reply, $grouped_replies, $current_user, $question);
                        if (strpos($html, 'vogo-private-reply-placeholder') !== false) {
                            $private_hidden++;
                        } else {
                            $output .= $html;
                        }
                    }
                    if ($private_hidden > 0) {
                        echo '<div style="font-style: italic; color: #999; margin-bottom: 15px;">' . $private_hidden . ' răspuns' . ($private_hidden > 1 ? 'uri private sunt' : ' privat este') . ' ascuns' . ($private_hidden > 1 ? 'e' : '') . ' pentru tine.</div>';
                    }
                    echo $output;
                }
            }
            vogo_render_replies($grouped_replies[0] ?? [], $grouped_replies, $current_user, $question);
        ?>
        </div>
        </div>

        <?php if (is_user_logged_in()) : ?>
            <div class="vogo-post-reply-wrapper">
                <!-- <h3 class="vogo-post-reply-title">Scrie un răspuns</h3> -->
                <form method="post" enctype="multipart/form-data" class="vogo-reply-form vogo-main-reply-form">
											
                    <textarea class="vogo-editor-simple" name="reply_content" rows="4" placeholder="Adaugă un răspuns..."></textarea>
					<div class="icon-submit-wrapper">
  <input type="submit" value="">
 <svg xmlns="http://www.w3.org/2000/svg" width="25px"  viewBox="0 0 24 24" fill="none">
  <path d="M1.40009 3.74301C1.24396 3.46365 1.18127 3.14168 1.22119 2.82415C1.2611 2.50662 1.40153 2.21018 1.62194 1.97815C1.84235 1.74612 2.13119 1.59067 2.44625 1.53451C2.76131 1.47835 3.08608 1.52443 3.37309 1.66601L22.3871 11.325C22.5105 11.3878 22.6141 11.4834 22.6864 11.6014C22.7588 11.7194 22.7971 11.8551 22.7971 11.9935C22.7971 12.1319 22.7588 12.2676 22.6864 12.3856C22.6141 12.5036 22.5105 12.5993 22.3871 12.662L3.37309 22.334C3.08608 22.4756 2.76131 22.5217 2.44625 22.4655C2.13119 22.4094 1.84235 22.2539 1.62194 22.0219C1.40153 21.7898 1.2611 21.4934 1.22119 21.1759C1.18127 20.8583 1.24396 20.5364 1.40009 20.257L6.45809 11.993L1.40009 3.74301Z" stroke="#009d81" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M22.7971 11.993H6.45312" stroke="#009d81" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
</div>
                    <div class="vogo-main-reply-form-options">
<input type="file" name="reply_image" id="vogo-file-upload" accept="image/*" style="display:none;">
                        <label for="vogo-file-upload" class="vogo-file-label" style="display:inline-block; padding:6px 16px; background:#fff; color:#fff; border-radius:5px; cursor:pointer; font-size:14px; z-index:2; position:relative; font-family: "gotham-book", Arial, Helvetica, sans-serif;"><svg xmlns="http://www.w3.org/2000/svg" fill="#009d81" width="24px" height="22px"  viewBox="0 0 1024 1024"><path d="M172.72 1007.632c-43.408 0-85.085-17.965-118.301-51.213-73.648-73.888-73.648-194.063-.017-267.903L628.674 78.692c89.6-89.744 226.848-81.68 327.008 18.608 44.88 44.96 70.064 109.776 69.12 177.904-.945 67.409-27.28 131.92-72.289 177.008L518.497 914.26c-12.08 12.945-32.336 13.536-45.231 1.393-12.864-12.16-13.488-32.448-1.36-45.345l434.672-462.752c34-34.064 53.504-82.385 54.223-133.249.72-50.895-17.664-98.88-50.368-131.664-61.44-61.568-161.473-93.808-235.841-19.264L100.336 733.203c-49.376 49.503-49.36 129.008-.64 177.855 22.847 22.864 49.967 34 78.847 32.255 28.576-1.744 57.952-16.4 82.72-41.232L718.19 415.745c16.56-16.592 49.84-57.264 15.968-91.216-19.184-19.216-32.656-18.032-37.087-17.664-12.656 1.12-27.44 9.872-42.784 25.264l-343.92 365.776c-12.144 12.912-32.416 13.536-45.233 1.36-12.88-12.128-13.472-32.448-1.36-45.312L608.32 287.489c27.088-27.216 54.784-41.968 82.976-44.496 22-1.953 54.72 2.736 88.096 36.208 49.536 49.631 43.376 122.432-15.28 181.216L307.184 946.72c-36.48 36.608-80.529 57.872-124.721 60.591-3.248.224-6.496.32-9.744.32z"/></svg>

</label>
						                        <div id="vogo-file-tooltip" style="display:none; position:absolute; left:0; top:0; background:#222; color:#fff; padding:6px 14px; border-radius:6px; font-size:13px; z-index:1000; white-space:nowrap;"></div>

                        
<!--                         <input type="submit" value="Răspunde"> -->
                    </div>
<div style="display: flex; gap: 10px;">
                            <label><input type="radio" name="reply_visibility" value="public" checked> Public</label>
                            <label><input type="radio" name="reply_visibility" value="private"> Privat</label>
                        </div>
                    <input type="hidden" name="vogo_question_id" value="<?php echo esc_attr($question_id); ?>">
                    <input type="hidden" name="action" value="vogo_submit_reply">
                </form>
                <div id="vogo-reply-message"></div>
            </div>
        <?php else : ?>
            <p class="vogo-login-message"><a href="/login">Autentifică-te</a> pentru a răspunde.</p>
        <?php endif; ?>

        <?php if (is_user_logged_in()) : ?>
            <script>
            // Event delegation for toggling nested reply forms
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('vogo-reply-toggle')) {
                    const replyId = e.target.dataset.replyId;
                    const form = document.getElementById('reply-form-' + replyId);
                    if (form) {
                        form.style.display = (form.style.display === 'none') ? 'block' : 'none';
                    }
                }
            });

            document.addEventListener('DOMContentLoaded', function () {
                // Submission protection for top-level reply form
                let isSubmitting = false;

                // AJAX for top-level reply form only
                const form = document.querySelector('.vogo-main-reply-form');
                const messageBox = document.getElementById('vogo-reply-message');
                const repliesList = document.getElementById('vogo-replies-list');

                if (form) {
                    // Prevent multiple AJAX bindings for the main reply form
                    if (form.classList.contains('reply-bound')) return;
                    form.classList.add('reply-bound');
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        if (isSubmitting) return;
                        isSubmitting = true;
                        const submitBtn = this.querySelector('input[type="submit"]');
                        submitBtn.disabled = true;
//                         submitBtn.value = "Se trimite...";
                        const textarea = form.querySelector('textarea');
                        const content = textarea.value.trim();
                        if (!content) {
                            messageBox.innerHTML = '<p style="color:red;">Te rugăm să scrii un răspuns.</p>';
                            isSubmitting = false;
                            submitBtn.disabled = false;
//                             submitBtn.value = "Răspunde";
                            return;
                        }
                        const formData = new FormData(form);
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            isSubmitting = false;
                            submitBtn.disabled = false;
//                             submitBtn.value = "Răspunde";
                            if (data && data.success && data.reply_html) {
                                // Inject new reply HTML directly and reset form
                                repliesList.innerHTML += data.reply_html;
                                form.reset();
                            } else {
                                console.error("Reply HTML missing or malformed:", data);
                                messageBox.innerHTML = '<p style="color:red;">Trimiterea răspunsului a eșuat.</p>';
                            }
                        })
                        .catch(() => {
                            isSubmitting = false;
                            submitBtn.disabled = false;
//                             submitBtn.value = "Răspunde";
                            messageBox.innerHTML = '<p style="color:red;">Trimiterea răspunsului a eșuat.</p>';
                        });
                    });
                }

                // AJAX for nested reply forms
                document.querySelectorAll('.vogo-reply-form').forEach(function(nestedForm){
                    // Don't double-bind for the top-level form
                    if (nestedForm.closest('#vogo-reply-message')) return;
                    // Prevent multiple AJAX bindings
                    if (nestedForm.classList.contains('reply-bound')) return;
                    nestedForm.classList.add('reply-bound');
                    // Submission protection for each nested form
                    let isSubmitting = false;
                    nestedForm.addEventListener('submit', function(e){
                        e.preventDefault();
                        if (isSubmitting) return;
                        isSubmitting = true;
                        const submitBtn = this.querySelector('input[type="submit"]');
                        submitBtn.disabled = true;
//                         submitBtn.value = "Se trimite...";
                        const textarea = nestedForm.querySelector('textarea');
                        const content = textarea.value.trim();
                        if (!content) {
                            alert('Te rugăm să scrii un răspuns.');
                            isSubmitting = false;
                            submitBtn.disabled = false;
//                             submitBtn.value = "Răspunde";
                            return;
                        }
                        const formData = new FormData(nestedForm);
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            isSubmitting = false;
                            submitBtn.disabled = false;
//                             submitBtn.value = "Răspunde";
                            if (data && data.success && data.reply_html) {
                                // Insert the new reply into the correct .vogo-child-comment-list
                                const parentReplyId = nestedForm.querySelector('[name="parent_reply_id"]').value;
                                const container = document.getElementById('child-comments-' + parentReplyId);

                                // Ensure the child list is visible before appending
                                if (container) {
                                    container.style.display = 'block';
                                    container.insertAdjacentHTML('beforeend', data.reply_html);
                                } else {
                                    // Fallback (append below form)
                                    const replyBlock = nestedForm.closest('.vogo-reply-block');
                                    if (replyBlock) {
                                        replyBlock.insertAdjacentHTML('beforeend', data.reply_html);
                                    }
                                }
                                nestedForm.reset();
                            } else {
                                console.error("Reply HTML missing or malformed:", data);
                                alert('Trimiterea răspunsului a eșuat.');
                            }
                        })
                        .catch(() => {
                            isSubmitting = false;
                            submitBtn.disabled = false;
//                             submitBtn.value = "Răspunde";
                            alert('Trimiterea răspunsului a eșuat.');
                        });
                    });
                });
            });
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.vogo-toggle-child-comments').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const target = document.getElementById(this.dataset.target);
                        if (!target) return;
                        const isOpen = target.style.display === 'block';
                        target.style.display = isOpen ? 'none' : 'block';
                        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="14" height="14" style="fill:currentColor;"><path d="M512 240c0 114.9-114.6 208-256 208c-37.1 0-72.3-6.4-104.1-17.9c-11.9 8.7-31.3 20.6-54.3 30.6C73.6 471.1 44.7 480 16 480c-6.5 0-12.3-3.9-14.8-9.9c-2.5-6-1.1-12.8 3.4-17.4l.3-.3c.3-.3 .7-.7 1.3-1.4c1.1-1.2 2.8-3.1 4.9-5.7c4.1-5 9.6-12.4 15.2-21.6c10-16.6 19.5-38.4 21.4-62.9C17.7 326.8 0 285.1 0 240C0 125.1 114.6 32 256 32s256 93.1 256 208z"/></svg>';
                    });
                });
            });
				 document.addEventListener('DOMContentLoaded', function() {
                var fileInput = document.getElementById('vogo-file-upload');
                var fileLabel = document.querySelector('.vogo-file-label');
                var tooltip = document.getElementById('vogo-file-tooltip');
                var tooltipTimeout;
                if(fileInput && fileLabel && tooltip) {
                    fileLabel.parentNode.style.position = 'relative'; // Ensure parent is positioned
                    fileInput.addEventListener('change', function() {
                        if(this.files.length) {
                            tooltip.textContent = this.files[0].name;
                            tooltip.style.display = 'block';
                            // Position tooltip below the label
                            var rect = fileLabel.getBoundingClientRect();
//                             tooltip.style.left = fileLabel.offsetLeft + 'px';
//                             tooltip.style.top = (fileLabel.offsetTop + fileLabel.offsetHeight + 0) + 'px';
                            clearTimeout(tooltipTimeout);
                            tooltipTimeout = setTimeout(function(){
                                tooltip.style.display = 'none';
                            }, 3000);
                        }
                    });
                    // Hide tooltip on click anywhere
                    document.addEventListener('click', function(e) {
                        if (!fileLabel.contains(e.target)) {
                            tooltip.style.display = 'none';
                            clearTimeout(tooltipTimeout);
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}


// AJAX handler for reply submission
add_action('wp_ajax_vogo_submit_reply', 'vogo_ajax_submit_reply');
function vogo_ajax_submit_reply() {
    ob_clean();
    if (!is_user_logged_in()) {
        wp_send_json_error();
    }

    $question_id = intval($_POST['vogo_question_id']);
    // Use wp_kses_post to allow HTML (from TinyMCE)
    $reply_content = isset($_POST['reply_content']) ? wp_kses_post($_POST['reply_content']) : '';
    $visibility = (isset($_POST['reply_visibility']) && $_POST['reply_visibility'] === 'private') ? 'private' : 'public';
    $parent_reply_id = isset($_POST['parent_reply_id']) ? intval($_POST['parent_reply_id']) : 0;

    $reply_id = wp_insert_post([
        'post_type' => 'reply',
        'post_status' => 'publish',
        'post_content' => $reply_content,
        'post_author' => get_current_user_id(), // explicitly set author
    ]);

    if ($reply_id) {
        update_post_meta($reply_id, 'question_id', $question_id);
        update_post_meta($reply_id, 'visibility', $visibility);
        if ($parent_reply_id) update_post_meta($reply_id, 'parent_reply_id', $parent_reply_id);

        // Handle image upload
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!empty($_FILES['reply_image']['name'])) {
            $attachment_id = media_handle_upload('reply_image', 0);
            if (is_wp_error($attachment_id)) {
                ob_clean();
                wp_send_json_error(['message' => 'Image upload failed.']);
            } else {
                $image_url = wp_get_attachment_url($attachment_id);
                $reply_content .= '<div><img src="' . esc_url($image_url) . '" style="max-width:100%; margin-top:10px;"></div>';
            }
        }
        // Always update the post content, even if no image was uploaded (ensures reply_content is saved and not blank)
        wp_update_post([
            'ID' => $reply_id,
            'post_content' => $reply_content
        ]);

        // Use the global rendering function for a single reply
        ob_clean();
        clean_post_cache($reply_id); // Clear object cache
        $reply = get_post($reply_id); // Reload with updated content
        // Ensure reply object has the latest content for rendering
        $reply->post_content = $reply_content;
        // Only render the single reply block, no children
        $grouped_replies = [$reply->ID => []];
        // Add debug comment to reply_html
        // Fire action hook to notify question author of new reply
        do_action('vogo_new_reply_submitted', $reply_id, $question_id);
        $reply_html = "<!-- Rendered via AJAX -->\n" . vogo_render_single_reply($reply, $grouped_replies, get_current_user_id(), get_post($question_id));
        // Fallback if reply_html is empty (prevents "undefined" on AJAX)
        if (empty($reply_html)) {
            wp_send_json_error(['message' => 'Empty reply HTML']);
        }
        wp_send_json([
            'success' => true,
            'reply_html' => $reply_html
            ]);
    }

    ob_clean();
    wp_send_json_error();
}

add_shortcode('vogo_ask_question_form', 'vogo_render_question_form');
function vogo_render_question_form() {
    if (!is_user_logged_in()) {
        return '<div style="max-width:600px; margin:40px auto; padding:20px; background:#fefefe; border:1px solid #ddd; border-radius:8px; text-align:center;">
            <p style="font-size:16px;">Trebuie de asemenea să <a href="/login" style="color:#1d72b8; text-decoration:underline;">Autentificare</a> pentru a pune o întrebare.</p>
        </div>';
    }
    $prefill_city = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
    $prefill_interest = isset($_GET['interest']) ? sanitize_text_field($_GET['interest']) : '';
    // Load cities from JSON file
    $cities_file = get_stylesheet_directory() . '/inc/data/cities.json';
    $cities_json = file_exists($cities_file) ? json_decode(file_get_contents($cities_file), true) : [];
    $cities = isset($cities_json['cities']) ? $cities_json['cities'] : [];

    // Predefined interests
    $interests = [
        'Restaurant',
        'Hotel',
        'Agentie de turism',
        'Transport',
        'Livrarea alimentelor',
        'Asistență medicală',
        'Casa si gradina',
        'Coaching',
        'Activitati copii',
        'Psiholog',
        'Altele'
    ];

    ob_start();
    ?>
    
    <form method="post" enctype="multipart/form-data" style="max-width: 600px; margin: auto; padding: 20px; background: #fff; border: 1px solid #fff; border-radius: 4px;">
        <!-- <label style="display:block; margin-bottom:5px;">Titlu:</label> -->
        <div class="floating-group">
        <input type="text" name="question_title" id="question_title" placeholder=" " required style="width:100%; padding:8px !important; border:1px solid #ccc; border-radius:2px;">
        <label for="text">Titlu</label></div>
 <div class="floating-group">
        <!-- <label style="display:block; margin-bottom:5px;">Descriere:</label> -->
        <textarea name="question_content" id="question_content" placeholder=" " rows="5" required style="width:100%; border-radius:2px;padding:8px !important;"></textarea>
    <label for="question_content">Descriere</label></div>

        <!-- <label style="display:block; margin-bottom:5px;">Oraș:</label> -->
        <select name="question_city" required style="width:100%; padding:8px; margin-bottom:15px; border:1px solid #ccc; border-radius:2px;">
            <option value="">Selectează orașul</option>
            <?php foreach ($cities as $city): ?>
                <option value="<?php echo esc_attr($city); ?>" <?php selected($prefill_city, $city); ?> class="notranslate"><?php echo esc_html($city); ?></option>
            <?php endforeach; ?>
        </select>

        <!-- <label style="display:block; margin-bottom:5px;">Interes:</label> -->
        <select name="question_interest" required style="width:100%; padding:8px; margin-bottom:15px; border:1px solid #ccc; border-radius:2px;">
            <option value="">Selectează interesul</option>
            <?php foreach ($interests as $interest): ?>
                <option value="<?php echo esc_attr($interest); ?>" <?php selected($prefill_interest, $interest); ?> class="notranslate"><?php echo esc_html($interest); ?></option>
            <?php endforeach; ?>
        </select>

        <label style="display:block; margin-bottom:5px;">Imagine (opțional):</label>
        <input type="file" name="question_image" accept="image/*" style="margin-bottom:15px;">

        <?php wp_nonce_field('vogo_submit_question_nonce', 'vogo_nonce_field'); ?>
        <input type="submit" name="vogo_submit_question" value="Trimite întrebarea" style="padding:10px 20px; background:#30653e; color:white; border:none; border-radius:2px; cursor:pointer;">
    </form>
    <?php
    return ob_get_clean();
}

add_action('init', 'vogo_handle_question_submission');
function vogo_handle_question_submission() {
    if (!isset($_POST['vogo_submit_question']) || !is_user_logged_in()) return;

    // Prevent duplicate submissions with transient
    $user_id = get_current_user_id();
    if (get_transient('vogo_question_lock_' . $user_id)) return;
    set_transient('vogo_question_lock_' . $user_id, 1, 10); // Lock for 10 seconds

    // Nonce check for form validation
    if (!isset($_POST['vogo_nonce_field']) || !wp_verify_nonce($_POST['vogo_nonce_field'], 'vogo_submit_question_nonce')) return;

    $title = sanitize_text_field($_POST['question_title']);
    $content = sanitize_textarea_field($_POST['question_content']);
    $city = sanitize_text_field($_POST['question_city']);
    $interest = sanitize_text_field($_POST['question_interest']);

    $question_id = wp_insert_post([
        'post_type' => 'question',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_content' => $content,
        'post_author' => get_current_user_id()
    ]);

    if ($question_id) {
        update_post_meta($question_id, 'city', $city);
        update_post_meta($question_id, 'interest', $interest);

        // Immediate image upload
        if (!empty($_FILES['question_image']['name'])) {
            if (!function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            $attachment_id = media_handle_upload('question_image', $question_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($question_id, $attachment_id);
            }
        }

        // Trigger email notification or other actions
        do_action('vogo_new_question_submitted', $question_id);

        // Redirect immediately for faster UX
        wp_redirect(home_url('/forum'));
        exit;
    }
}

add_shortcode('vogo_question_list', 'vogo_render_question_list');
function vogo_render_question_list() {
    ob_start();

    // Add CSS classes at the top
    ?>
    <?php

    // Load cities from child theme JSON file
    $cities_file = get_stylesheet_directory() . '/inc/data/cities.json';
    $cities_json = file_exists($cities_file) ? json_decode(file_get_contents($cities_file), true) : [];
    $cities = isset($cities_json['cities']) ? $cities_json['cities'] : [];

    // Predefined interests
    $interests = [
        'Restaurant',
        'Hotel',
        'Agentie de turism',
        'Transport',
        'Livrarea alimentelor',
        'Asistență medicală',
        'Casa si gradina',
        'Coaching',
        'Activitati copii',
        'Psiholog',
        'Altele'
    ];

    // Handle filters
    $city_filter = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
    $interest_filter = isset($_GET['interest']) ? sanitize_text_field($_GET['interest']) : '';
    $paged = max(1, get_query_var('paged') ?: get_query_var('page'));

    // Query args
    $meta_query = ['relation' => 'AND'];
    if ($city_filter) {
        $meta_query[] = [
            'key' => 'city',
            'value' => $city_filter,
            'compare' => 'LIKE'
        ];
    }
    if ($interest_filter) {
        $meta_query[] = [
            'key' => 'interest',
            'value' => $interest_filter,
            'compare' => 'LIKE'
        ];
    }

    $query = new WP_Query([
        'post_type' => 'question',
        'posts_per_page' => 10,
        'paged' => $paged,
        'meta_query' => $meta_query
    ]);

    echo '<div class="vogo-filter-container">';
    ?>
    <form method="get" class="vogo-filter-form">

        <select name="city" class="vogo-select2" style="height:40px !important;">
            <option value=""><?php echo esc_html('Toate orașele'); ?></option>
            <?php foreach ($cities as $city): ?>
                <option class="notranslate" value="<?php echo esc_attr($city); ?>" <?php selected($city_filter, $city); ?>>
                    <?php echo esc_html($city); ?>
                </option>
            <?php endforeach; ?>
        </select>


        <select name="interest" class="vogo-select2" style="height:40px !important;">
            <option value=""><?php echo esc_html('Toate domeniile'); ?></option>
            <?php foreach ($interests as $interest): ?>
                <option value="<?php echo esc_attr($interest); ?>" <?php selected($interest_filter, $interest); ?>>
                    <?php echo esc_html($interest); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="/ask-a-question?city=<?php echo urlencode($city_filter); ?>&interest=<?php echo urlencode($interest_filter); ?>" class="vogo-ask-btn">
            <?xml version="1.0" ?><svg height="20" id="svg8" version="1.1" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:svg="http://www.w3.org/2000/svg"><defs id="defs2"/><g id="g2140" style="display:inline" transform="translate(0,-290.65039)"><path d="m 6,292.65039 c -2.1987,0 -4,1.8013 -4,4 v 12 c 0,2.1987 1.8013,4 4,4 h 12 c 2.1987,0 4,-1.8013 4,-4 v -12 c 0,-2.1987 -1.8013,-4 -4,-4 z m 0,2 h 12 c 1.1253,0 2,0.8747 2,2 v 12 c 0,1.1253 -0.8747,2 -2,2 H 6 c -1.1253,0 -2,-0.8747 -2,-2 v -12 c 0,-1.1253 0.8747,-2 2,-2 z" id="rect2131" style="color:#fff;font-style:normal;font-variant:normal;font-weight:normal;font-stretch:normal;font-size:medium;line-height:normal;font-family:sans-serif;font-variant-ligatures:normal;font-variant-position:normal;font-variant-caps:normal;font-variant-numeric:normal;font-variant-alternates:normal;font-variant-east-asian:normal;font-feature-settings:normal;font-variation-settings:normal;text-indent:0;text-align:start;text-decoration:none;text-decoration-line:none;text-decoration-style:solid;text-decoration-color:#fff;letter-spacing:normal;word-spacing:normal;text-transform:none;writing-mode:lr-tb;direction:ltr;text-orientation:mixed;dominant-baseline:auto;baseline-shift:baseline;text-anchor:start;white-space:normal;shape-padding:0;shape-margin:0;inline-size:0;clip-rule:nonzero;display:inline;overflow:visible;visibility:visible;opacity:1;isolation:auto;mix-blend-mode:normal;color-interpolation:sRGB;color-interpolation-filters:linearRGB;solid-color:#fff;solid-opacity:1;vector-effect:none;fill:#fff;fill-opacity:1;fill-rule:nonzero;stroke:none;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:4;stroke-dasharray:none;stroke-dashoffset:0;stroke-opacity:1;color-rendering:auto;image-rendering:auto;shape-rendering:auto;text-rendering:auto;enable-background:accumulate;stop-color:#fff;stop-opacity:1"/><path d="m 12,296.64998 a 1,1 0 0 0 -1,1 v 4 H 7 a 1,1 0 0 0 -1,1 1,1 0 0 0 1,1 h 4 v 4 a 1,1 0 0 0 1,1 1,1 0 0 0 1,-1 v -4 h 4 a 1,1 0 0 0 1,-1 1,1 0 0 0 -1,-1 h -4 v -4 a 1,1 0 0 0 -1,-1 z" id="path2133" style="color:#fff;font-style:normal;font-variant:normal;font-weight:normal;font-stretch:normal;font-size:medium;line-height:normal;font-family:sans-serif;font-variant-ligatures:normal;font-variant-position:normal;font-variant-caps:normal;font-variant-numeric:normal;font-variant-alternates:normal;font-variant-east-asian:normal;font-feature-settings:normal;font-variation-settings:normal;text-indent:0;text-align:start;text-decoration:none;text-decoration-line:none;text-decoration-style:solid;text-decoration-color:#fff;letter-spacing:normal;word-spacing:normal;text-transform:none;writing-mode:lr-tb;direction:ltr;text-orientation:mixed;dominant-baseline:auto;baseline-shift:baseline;text-anchor:start;white-space:normal;shape-padding:0;shape-margin:0;inline-size:0;clip-rule:nonzero;display:inline;overflow:visible;visibility:visible;opacity:1;isolation:auto;mix-blend-mode:normal;color-interpolation:sRGB;color-interpolation-filters:linearRGB;solid-color:#fff;solid-opacity:1;vector-effect:none;fill:#fff;fill-opacity:1;fill-rule:nonzero;stroke:none;stroke-linecap:round;stroke-linejoin:miter;stroke-miterlimit:4;stroke-dasharray:none;stroke-dashoffset:0;stroke-opacity:1;color-rendering:auto;image-rendering:auto;shape-rendering:auto;text-rendering:auto;enable-background:accumulate;stop-color:#fff;stop-opacity:1"/></g></svg>
        </a>

    </form>

    <!-- Select2 assets and initialization -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery !== 'undefined') {
            jQuery('.vogo-select2').select2({
                width: 'resolve',
                templateResult: function (data) {
                    if (!data.id) return data.text;
                    return jQuery('<span class="notranslate" translate="no">' + data.text + '</span>');
                },
                templateSelection: function (data) {
                    return jQuery('<span class="notranslate" translate="no">' + data.text + '</span>');
                }
            }).on('change', function () {
                const form = jQuery(this).closest('form');
                jQuery('#vogo-question-loader').show();
                form.submit();
            }).each(function () {
                jQuery(this).next('.select2').addClass('notranslate').attr('translate', 'no');
            });
        }
    });
    </script>
    <?php
    // Loader container (hidden by default, shows when loading)
    echo '<div id="vogo-question-loader" style="display:none; text-align:center; margin:20px 0;">
        <span style="padding:10px 20px; background:#f0f0f0; border-radius:4px;">Se filtrează... vă rugăm așteptați</span>
    </div>';
    echo '</div>';

    // Wrap the results in a container for loader targeting
    echo '<div id="vogo-question-results">';

    // List questions
    if ($query->have_posts()) {
        echo '<ul class="vogo-question-list">';
        while ($query->have_posts()) {
            $query->the_post();
            $city = get_post_meta(get_the_ID(), 'city', true);
            $interest = get_post_meta(get_the_ID(), 'interest', true);
            $author_id = get_post_field('post_author', get_the_ID());
            $author_name = get_the_author_meta('display_name', $author_id);
            $publish_time = get_the_time('h:i A');
            $first_letter = strtoupper(mb_substr($author_name, 0, 1));
            $avatar_url = get_user_meta($author_id, 'custom_avatar', true);
            $created_date = get_the_date('d M Y');
            // Begin li
            echo '<li class="vogo-question-item" style="display:block; margin-bottom:0px; position:relative;">';
            // Begin a (covering all content inside li)
            echo '<a href="https://test07.vogo.family/question-thread/?question_id=' . get_the_ID() . '&city=' . urlencode($city_filter) . '&interest=' . urlencode($interest_filter) . '" style="display:flex; align-items:flex-start; gap:16px; text-decoration:none; color:inherit; width:100%;">';
            // Time at top right
            echo '<span style="position:absolute; top:5px; right:9px; font-size:0.85em; color:#999;">' . esc_html($publish_time) . '</span>';
            // Left: Image
            if ($avatar_url) {
                echo '<div class="vogo-question-image" style="flex:0 0 64px; max-width:44px;"><img src="' . esc_url($avatar_url) . '" style="width:44px; height:44px; border-radius:100px; object-fit:cover;"></div>';
            } else {
                echo '<div class="vogo-question-image" style="flex:0 0 64px; max-width:44px; width:44px; height:44px; background:#337ab7; border-radius:100px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; font-weight:bold;">' . esc_html($first_letter) . '</div>';
            }
            // Right: Author, Title and meta
            echo '<div style="flex:1; min-width:49%;">';
            echo '<span class="vogo-question-author" style="font-size:0.95em; color:#337ab7; font-weight:500; margin-right:6px; display:block;">' . esc_html($author_name) . '</span>';
            echo '<span class="vogo-question-title-link" style="font-weight:600;">' . wp_trim_words(get_the_title(), 12, '...') . '</span>';
            echo '<div class="vogo-qna-meta" style="font-size:13px; color:#777; margin-top:4px;"><span class="notranslate" style="color:#777"> ' . esc_html($city) . ' </span> | ' . esc_html($interest) . ' | ' . esc_html($created_date) . '</div>';
            echo '</div>';
            // End a
            echo '</a>';
            // End li
            echo '</li>';
        }
        echo '</ul>';

        // Pagination (preserve city and interest filters)
        echo paginate_links([
            'total' => $query->max_num_pages,
            'current' => $paged,
            'format' => '?paged=%#%',
            'add_args' => [
                'city' => $city_filter,
                'interest' => $interest_filter,
            ],
        ]);
    } else {
        echo '<p>Nu s-au găsit întrebări.</p>';
    }

    echo '</div>'; // close #vogo-question-results

    wp_reset_postdata();
    return ob_get_clean();
}
add_filter('manage_reply_posts_columns', function ($columns) {
    $columns['question'] = 'Question';
    $columns['parent_reply'] = 'Reply To';
    return $columns;
});

add_action('manage_reply_posts_custom_column', function ($column, $post_id) {
    if ($column === 'question') {
        $question_id = get_post_meta($post_id, 'question_id', true);
        if ($question_id) {
            $title = get_the_title($question_id);
            echo '<a href="' . get_edit_post_link($question_id) . '">' . esc_html($title) . '</a>';
        } else {
            echo '—';
        }
    }

    if ($column === 'parent_reply') {
        $parent_id = get_post_meta($post_id, 'parent_reply_id', true);
        if ($parent_id) {
            echo '<a href="' . get_edit_post_link($parent_id) . '">Reply #' . intval($parent_id) . '</a>';
        } else {
            echo '—';
        }
    }

}, 10, 2);

// Show trimmed reply content as title if reply has no title (for admin list table)
add_filter('the_title', function ($title, $post_id) {
    $post = get_post($post_id);
    if ($post && $post->post_type === 'reply' && empty($title)) {
        $content = strip_tags(get_post_field('post_content', $post_id));
        return wp_trim_words($content, 10, '...');
    }
    return $title;
}, 10, 2);