<?php

namespace Zephyrisle\ZaiBot\Service;

use Flarum\Extension\ExtensionManager;

class ExtensionIntegrationService
{
    private const INTEGRATIONS = [
        ['id' => 'flarum-realtime', 'package' => 'flarum/realtime', 'label' => 'Realtime', 'group' => 'platform', 'capabilities' => ['realtime', 'notifications'], 'mode' => 'context'],
        ['id' => 'flarum-flags', 'package' => 'flarum/flags', 'label' => 'Flags', 'group' => 'moderation', 'capabilities' => ['report', 'moderation'], 'mode' => 'tool'],
        ['id' => 'flarum-tags', 'package' => 'flarum/tags', 'label' => 'Tags', 'group' => 'content', 'capabilities' => ['tags', 'taxonomy'], 'mode' => 'context'],
        ['id' => 'flarum-approval', 'package' => 'flarum/approval', 'label' => 'Approval', 'group' => 'moderation', 'capabilities' => ['approval', 'moderation'], 'mode' => 'context'],
        ['id' => 'flarum-suspend', 'package' => 'flarum/suspend', 'label' => 'Suspend', 'group' => 'moderation', 'capabilities' => ['suspension', 'moderation'], 'mode' => 'context'],
        ['id' => 'flarum-gdpr', 'package' => 'flarum/gdpr', 'label' => 'GDPR', 'group' => 'security', 'capabilities' => ['privacy', 'compliance'], 'mode' => 'context'],
        ['id' => 'flarum-mentions', 'package' => 'flarum/mentions', 'label' => 'Mentions', 'group' => 'social', 'capabilities' => ['mentions', 'user-links'], 'mode' => 'context'],
        ['id' => 'flarum-subscriptions', 'package' => 'flarum/subscriptions', 'label' => 'Subscriptions', 'group' => 'social', 'capabilities' => ['subscriptions', 'follow'], 'mode' => 'context'],
        ['id' => 'fof-user-directory', 'package' => 'fof/user-directory', 'label' => 'User Directory', 'group' => 'profile', 'capabilities' => ['directory', 'discovery'], 'mode' => 'context'],
        ['id' => 'fof-follow-tags', 'package' => 'fof/follow-tags', 'label' => 'Follow Tags', 'group' => 'social', 'capabilities' => ['tag-follow', 'subscriptions'], 'mode' => 'context'],
        ['id' => 'flarum-markdown', 'package' => 'flarum/markdown', 'label' => 'Markdown', 'group' => 'formatting', 'capabilities' => ['markdown', 'formatting'], 'mode' => 'context'],
        ['id' => 'fof-byobu', 'package' => 'fof/byobu', 'label' => 'Byobu', 'group' => 'messaging', 'capabilities' => ['private-discussions', 'messaging'], 'mode' => 'context'],
        ['id' => 'fof-upload', 'package' => 'fof/upload', 'label' => 'Upload', 'group' => 'content', 'capabilities' => ['uploads', 'attachments', 'file-analysis'], 'mode' => 'tool'],
        ['id' => 'flarum-bbcode', 'package' => 'flarum/bbcode', 'label' => 'BBCode', 'group' => 'formatting', 'capabilities' => ['bbcode', 'formatting'], 'mode' => 'context'],
        ['id' => 'flarum-lock', 'package' => 'flarum/lock', 'label' => 'Lock', 'group' => 'moderation', 'capabilities' => ['lock', 'discussion-state'], 'mode' => 'context'],
        ['id' => 'fof-default-user-preferences', 'package' => 'fof/default-user-preferences', 'label' => 'Default User Preferences', 'group' => 'profile', 'capabilities' => ['preferences'], 'mode' => 'context'],
        ['id' => 'flarum-likes', 'package' => 'flarum/likes', 'label' => 'Likes', 'group' => 'social', 'capabilities' => ['likes', 'reputation'], 'mode' => 'tool'],
        ['id' => 'zephyrisle-z-theme', 'package' => 'zephyrisle/z-theme', 'label' => 'Z Theme', 'group' => 'theme', 'capabilities' => ['theme', 'branding'], 'mode' => 'context'],
        ['id' => 'sycho-private-facade', 'package' => 'sycho/private-facade', 'label' => 'Private Facade', 'group' => 'security', 'capabilities' => ['privacy', 'facade'], 'mode' => 'context'],
        ['id' => 'ramon-verified', 'package' => 'ramon/verified', 'label' => 'Verified', 'group' => 'profile', 'capabilities' => ['verified-badges', 'trust-signals'], 'mode' => 'context'],
        ['id' => 'ramon-point-system', 'package' => 'ramon/point-system', 'label' => 'Point System', 'group' => 'gamification', 'capabilities' => ['points', 'gamification'], 'mode' => 'context'],
        ['id' => 'pianotell-flamoji', 'package' => 'pianotell/flamoji', 'label' => 'Flamoji', 'group' => 'social', 'capabilities' => ['emoji', 'reactions'], 'mode' => 'context'],
        ['id' => 'ianm-twofactor', 'package' => 'ianm/twofactor', 'label' => 'Two Factor', 'group' => 'security', 'capabilities' => ['2fa', 'security'], 'mode' => 'context'],
        ['id' => 'ianm-follow-users', 'package' => 'ianm/follow-users', 'label' => 'Follow Users', 'group' => 'social', 'capabilities' => ['follow-users', 'social-graph'], 'mode' => 'context'],
        ['id' => 'huseyinfiliz-stickiest', 'package' => 'huseyinfiliz/stickiest', 'label' => 'Stickiest', 'group' => 'content', 'capabilities' => ['sticky', 'sorting'], 'mode' => 'context'],
        ['id' => 'huseyinfiliz-rewind', 'package' => 'huseyinfiliz/rewind', 'label' => 'Rewind', 'group' => 'content', 'capabilities' => ['timeline', 'activity-history'], 'mode' => 'context'],
        ['id' => 'forumaker-profile-cover', 'package' => 'forumaker/profile-cover', 'label' => 'Profile Cover', 'group' => 'profile', 'capabilities' => ['profile-cover'], 'mode' => 'context'],
        ['id' => 'fof-user-bio', 'package' => 'fof/user-bio', 'label' => 'User Bio', 'group' => 'profile', 'capabilities' => ['profile-bio'], 'mode' => 'context'],
        ['id' => 'fof-terms', 'package' => 'fof/terms', 'label' => 'Terms', 'group' => 'security', 'capabilities' => ['terms', 'compliance'], 'mode' => 'context'],
        ['id' => 'fof-split', 'package' => 'fof/split', 'label' => 'Split', 'group' => 'moderation', 'capabilities' => ['split-discussion', 'moderation'], 'mode' => 'context'],
        ['id' => 'fof-socialprofile', 'package' => 'fof/socialprofile', 'label' => 'Social Profile', 'group' => 'profile', 'capabilities' => ['social-links', 'profile'], 'mode' => 'context'],
        ['id' => 'fof-rich-text', 'package' => 'fof/rich-text', 'label' => 'Rich Text', 'group' => 'formatting', 'capabilities' => ['rich-text', 'formatting'], 'mode' => 'context'],
        ['id' => 'fof-reactions', 'package' => 'fof/reactions', 'label' => 'Reactions', 'group' => 'social', 'capabilities' => ['reactions', 'engagement'], 'mode' => 'tool'],
        ['id' => 'fof-pwned-passwords', 'package' => 'fof/pwned-passwords', 'label' => 'Pwned Passwords', 'group' => 'security', 'capabilities' => ['password-security'], 'mode' => 'context'],
        ['id' => 'fof-profile-image-crop', 'package' => 'fof/profile-image-crop', 'label' => 'Profile Image Crop', 'group' => 'profile', 'capabilities' => ['avatars', 'profile-images'], 'mode' => 'context'],
        ['id' => 'fof-prevent-necrobumping', 'package' => 'fof/prevent-necrobumping', 'label' => 'Prevent Necrobumping', 'group' => 'moderation', 'capabilities' => ['necrobumps', 'discussion-rules'], 'mode' => 'context'],
        ['id' => 'fof-polls', 'package' => 'fof/polls', 'label' => 'Polls', 'group' => 'content', 'capabilities' => ['polls', 'voting'], 'mode' => 'context'],
        ['id' => 'fof-photoswipe', 'package' => 'fof/photoswipe', 'label' => 'PhotoSwipe', 'group' => 'content', 'capabilities' => ['images', 'media-viewer'], 'mode' => 'context'],
        ['id' => 'fof-pages', 'package' => 'fof/pages', 'label' => 'Pages', 'group' => 'content', 'capabilities' => ['pages', 'static-content'], 'mode' => 'context'],
        ['id' => 'fof-move-posts', 'package' => 'fof/move-posts', 'label' => 'Move Posts', 'group' => 'moderation', 'capabilities' => ['move-posts', 'moderation'], 'mode' => 'context'],
        ['id' => 'fof-merge-discussions', 'package' => 'fof/merge-discussions', 'label' => 'Merge Discussions', 'group' => 'moderation', 'capabilities' => ['merge-discussions', 'moderation'], 'mode' => 'context'],
        ['id' => 'fof-masquerade', 'package' => 'fof/masquerade', 'label' => 'Masquerade', 'group' => 'profile', 'capabilities' => ['custom-profile-fields'], 'mode' => 'context'],
        ['id' => 'fof-links', 'package' => 'fof/links', 'label' => 'Links', 'group' => 'content', 'capabilities' => ['navigation-links'], 'mode' => 'context'],
        ['id' => 'fof-linguist', 'package' => 'fof/linguist', 'label' => 'Linguist', 'group' => 'platform', 'capabilities' => ['language-overrides', 'localization'], 'mode' => 'context'],
        ['id' => 'fof-impersonate', 'package' => 'fof/impersonate', 'label' => 'Impersonate', 'group' => 'security', 'capabilities' => ['impersonation', 'admin-tools'], 'mode' => 'context'],
        ['id' => 'fof-geoip', 'package' => 'fof/geoip', 'label' => 'GeoIP', 'group' => 'profile', 'capabilities' => ['geoip', 'location'], 'mode' => 'context'],
        ['id' => 'fof-formatting', 'package' => 'fof/formatting', 'label' => 'Formatting', 'group' => 'formatting', 'capabilities' => ['formatting', 'text-plugins'], 'mode' => 'context'],
        ['id' => 'fof-drafts', 'package' => 'fof/drafts', 'label' => 'Drafts', 'group' => 'content', 'capabilities' => ['drafts', 'save-progress'], 'mode' => 'context'],
        ['id' => 'fof-discussion-views', 'package' => 'fof/discussion-views', 'label' => 'Discussion Views', 'group' => 'analytics', 'capabilities' => ['views', 'analytics'], 'mode' => 'context'],
        ['id' => 'fof-default-group', 'package' => 'fof/default-group', 'label' => 'Default Group', 'group' => 'profile', 'capabilities' => ['default-groups'], 'mode' => 'context'],
        ['id' => 'fof-clockwork', 'package' => 'fof/clockwork', 'label' => 'Clockwork', 'group' => 'debug', 'capabilities' => ['debug', 'profiling'], 'mode' => 'context'],
        ['id' => 'fof-best-answer', 'package' => 'fof/best-answer', 'label' => 'Best Answer', 'group' => 'content', 'capabilities' => ['best-answer', 'qna'], 'mode' => 'context'],
        ['id' => 'fof-bbcode-details', 'package' => 'fof/bbcode-details', 'label' => 'BBCode Details', 'group' => 'formatting', 'capabilities' => ['bbcode-details', 'formatting'], 'mode' => 'context'],
        ['id' => 'fof-badges', 'package' => 'fof/badges', 'label' => 'Badges', 'group' => 'gamification', 'capabilities' => ['badges', 'recognition'], 'mode' => 'context'],
        ['id' => 'flarum-sticky', 'package' => 'flarum/sticky', 'label' => 'Sticky', 'group' => 'content', 'capabilities' => ['sticky', 'discussion-state'], 'mode' => 'context'],
        ['id' => 'flarum-statistics', 'package' => 'flarum/statistics', 'label' => 'Statistics', 'group' => 'analytics', 'capabilities' => ['statistics', 'analytics'], 'mode' => 'context'],
        ['id' => 'flarum-nicknames', 'package' => 'flarum/nicknames', 'label' => 'Nicknames', 'group' => 'profile', 'capabilities' => ['nicknames', 'display-names'], 'mode' => 'context'],
        ['id' => 'flarum-messages', 'package' => 'flarum/messages', 'label' => 'Messages', 'group' => 'messaging', 'capabilities' => ['messages', 'direct-messages'], 'mode' => 'context'],
        ['id' => 'flarum-lang-english', 'package' => 'flarum/lang-english', 'label' => 'English Language Pack', 'group' => 'platform', 'capabilities' => ['language-pack', 'english'], 'mode' => 'context'],
        ['id' => 'flarum-lang-chinese-simplified', 'package' => 'flarum/lang-chinese-simplified', 'label' => 'Chinese Simplified Language Pack', 'group' => 'platform', 'capabilities' => ['language-pack', 'zh-cn'], 'mode' => 'context'],
        ['id' => 'flarum-emoji', 'package' => 'flarum/emoji', 'label' => 'Emoji', 'group' => 'social', 'capabilities' => ['emoji', 'expressiveness'], 'mode' => 'context'],
        ['id' => 'flarum-akismet', 'package' => 'flarum/akismet', 'label' => 'Akismet', 'group' => 'moderation', 'capabilities' => ['spam-detection', 'moderation'], 'mode' => 'context'],
        ['id' => 'datlechin-copy-links', 'package' => 'datlechin/copy-links', 'label' => 'Copy Links', 'group' => 'content', 'capabilities' => ['permalinks', 'sharing'], 'mode' => 'context'],
        ['id' => 'datlechin-birthdays', 'package' => 'datlechin/birthdays', 'label' => 'Birthdays', 'group' => 'profile', 'capabilities' => ['birthdays', 'profile-data'], 'mode' => 'context'],
        ['id' => 'antoinefr-money', 'package' => 'antoinefr/money', 'label' => 'Money', 'group' => 'gamification', 'capabilities' => ['money', 'economy'], 'mode' => 'context'],
        ['id' => 'acpl-mobile-tab', 'package' => 'acpl/mobile-tab', 'label' => 'Mobile Tab', 'group' => 'theme', 'capabilities' => ['mobile-navigation', 'theme'], 'mode' => 'context'],
    ];

    public function __construct(private ExtensionManager $extensions)
    {
    }

    public function catalog(): array
    {
        return array_map(function (array $integration) {
            $integration['enabled'] = $this->extensions->isEnabled($integration['id']);

            return $integration;
        }, self::INTEGRATIONS);
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->catalog(), static fn (array $integration) => $integration['enabled'] === true));
    }

    public function summary(): array
    {
        $catalog = $this->catalog();
        $enabled = array_values(array_filter($catalog, static fn (array $integration) => $integration['enabled'] === true));

        $capabilities = [];
        foreach ($enabled as $integration) {
            foreach ($integration['capabilities'] as $capability) {
                $capabilities[$capability][] = $integration['label'];
            }
        }

        ksort($capabilities);

        return [
            'totalCount' => count($catalog),
            'enabledCount' => count($enabled),
            'toolReadyCount' => count(array_filter($enabled, static fn (array $integration) => $integration['mode'] === 'tool')),
            'capabilities' => $capabilities,
            'enabledIds' => array_values(array_map(static fn (array $integration) => $integration['id'], $enabled)),
        ];
    }

    public function promptContext(): string
    {
        $enabled = $this->enabled();
        if ($enabled === []) {
            return '当前社区未检测到额外扩展联动能力，请仅使用基础论坛功能。';
        }

        $parts = [];
        foreach ($enabled as $integration) {
            $parts[] = sprintf(
                '%s: %s',
                $integration['label'],
                implode(', ', $integration['capabilities'])
            );
        }

        return "当前社区已启用以下扩展能力，可在回复中遵守对应语义和行为边界：\n- ".implode("\n- ", $parts);
    }

    public function toolDefinitions(): array
    {
        return [
            'like' => [
                'dependency' => 'flarum-likes',
                'target_types' => ['post', 'discussion'],
                'dangerous' => false,
            ],
            'report' => [
                'dependency' => 'flarum-flags',
                'target_types' => ['post', 'discussion', 'user'],
                'dangerous' => true,
            ],
            'reaction' => [
                'dependency' => 'fof-reactions',
                'target_types' => ['post'],
                'dangerous' => false,
            ],
            'analyze_upload' => [
                'dependency' => 'fof-upload',
                'target_types' => ['upload'],
                'dangerous' => false,
            ],
            'tag_discussion' => [
                'dependency' => 'flarum-tags',
                'target_types' => ['discussion'],
                'dangerous' => false,
            ],
            'approve_content' => [
                'dependency' => 'flarum-approval',
                'target_types' => ['post', 'discussion'],
                'dangerous' => true,
            ],
            'lock_discussion' => [
                'dependency' => 'flarum-lock',
                'target_types' => ['discussion'],
                'dangerous' => true,
            ],
            'sticky_discussion' => [
                'dependency' => 'flarum-sticky',
                'target_types' => ['discussion'],
                'dangerous' => true,
            ],
            'mark_best_answer' => [
                'dependency' => 'fof-best-answer',
                'target_types' => ['post'],
                'dangerous' => false,
            ],
            'follow_discussion' => [
                'dependency' => 'flarum-subscriptions',
                'target_types' => ['discussion'],
                'dangerous' => false,
            ],
            'follow_tag' => [
                'dependency' => 'fof-follow-tags',
                'target_types' => ['tag'],
                'dangerous' => false,
            ],
            'message_user' => [
                'dependency' => 'flarum-messages',
                'target_types' => ['user'],
                'dangerous' => true,
            ],
            'start_private_discussion' => [
                'dependency' => 'fof-byobu',
                'target_types' => ['user'],
                'dangerous' => true,
            ],
        ];
    }
}
