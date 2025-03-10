import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import { ComboboxControl } from '@wordpress/components';

const PostSelector = () => {
    const { editPost } = useDispatch('core/editor');
    const { parentId, searchResults } = useSelect((select) => {
        const { getEditedPostAttribute } = select('core/editor');
        const { getEntityRecords } = select('core');

        return {
            parentId: getEditedPostAttribute('parent'),
            searchResults: getEntityRecords('postType', 'post', {
                per_page: 10,
                _fields: ['id', 'title'],
            }),
        };
    }, []);

    const options = searchResults
        ? searchResults.map((post) => ({
            label: post.title.rendered,
            value: post.id,
        }))
        : [];

    return (
        <PluginDocumentSettingPanel
            name="live-update-parent"
            title={__('Associated Post', 'live-updates')}
            className="live-update-parent-panel"
        >
            <ComboboxControl
                label={__('Select Parent Post', 'live-updates')}
                value={parentId}
                onChange={(value) => editPost({ parent: parseInt(value) })}
                options={options}
            />
        </PluginDocumentSettingPanel>
    );
};

registerPlugin('live-update-post-selector', {
    render: PostSelector,
    icon: 'admin-links',
}); 