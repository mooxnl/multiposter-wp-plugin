import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

registerBlockType('multiposter/share-buttons', {
    title: __('Share Buttons', 'multiposter'),
    icon: 'share',
    category: 'widgets',
    attributes: {
        vacancyId: { type: 'string', default: '' },
    },
    edit: function Edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps();
        const { postId, postType } = useSelect((select) => ({
            postId: select('core/editor').getCurrentPostId(),
            postType: select('core/editor').getCurrentPostType(),
        }));
        const effectiveId = attributes.vacancyId || (postType === 'vacatures' ? postId : '');

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Settings', 'multiposter')}>
                        <TextControl
                            label={__('Vacancy ID (optional override)', 'multiposter')}
                            value={attributes.vacancyId}
                            onChange={(val) => setAttributes({ vacancyId: val })}
                            help={__('Leave empty to auto-detect from current post.', 'multiposter')}
                        />
                    </PanelBody>
                </InspectorControls>
                <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                    <strong>{__('Share Buttons', 'multiposter')}</strong>
                    {effectiveId ? (
                        <p>{__('Vacancy ID:', 'multiposter')} {effectiveId} {!attributes.vacancyId && postType === 'vacatures' ? __('(auto-detected)', 'multiposter') : ''}</p>
                    ) : (
                        <p style={{ color: '#cc0000' }}>{__('No vacancy detected. Edit a vacatures post or set a vacancy ID manually.', 'multiposter')}</p>
                    )}
                </div>
            </div>
        );
    },
    save: () => null,
});
