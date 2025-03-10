import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import DOMPurify from 'dompurify';

const sanitizeHTML = (html) => {
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: ['p', 'a', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'h5', 'h6'],
        ALLOWED_ATTR: ['href', 'target', 'rel', 'class']
    });
};

registerBlockType('live-updates/display', {
    apiVersion: 3,
    title: __('Live Updates Display', 'live-updates'),
    icon: 'update',
    category: 'widgets',
    attributes: {
        postId: {
            type: 'string',
            default: '',
        },
    },
    edit: ({ attributes, setAttributes }) => {
        const blockProps = useBlockProps();
        const { postId } = attributes;

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Settings', 'live-updates')}>
                        <TextControl
                            label={__('Post ID', 'live-updates')}
                            value={postId}
                            onChange={(value) => setAttributes({ postId: value })}
                            help={__('Enter the ID of the post to display updates for.', 'live-updates')}
                        />
                    </PanelBody>
                </InspectorControls>
                {postId ? (
                    <Placeholder
                        icon="update"
                        label={__('Live Updates Display', 'live-updates')}
                    >
                        <div
                            dangerouslySetInnerHTML={{ 
                                __html: sanitizeHTML(content)
                            }}
                        >
                        </div>
                    </Placeholder>
                ) : (
                    <Placeholder
                        icon="update"
                        label={__('Live Updates Display', 'live-updates')}
                        instructions={__('Please enter a Post ID in the block settings.', 'live-updates')}
                    />
                )}
            </div>
        );
    },
    save: () => null, // Dynamic block, rendered in PHP
}); 