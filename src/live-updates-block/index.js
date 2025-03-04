import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
                        <p>
                            {__('Displaying updates for post ID:', 'live-updates')} {postId}
                        </p>
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