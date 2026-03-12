import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

registerBlockType('multiposter/related-vacancies', {
    title: __('Related Vacancies', 'multiposter'),
    icon: 'networking',
    category: 'widgets',
    attributes: {
        vacancyId: { type: 'string', default: '' },
        count: { type: 'number', default: 3 },
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
                        <RangeControl
                            label={__('Number of vacancies', 'multiposter')}
                            value={attributes.count}
                            onChange={(val) => setAttributes({ count: val })}
                            min={1}
                            max={12}
                        />
                    </PanelBody>
                </InspectorControls>
                <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                    <strong>{__('Related Vacancies', 'multiposter')}</strong>
                    {effectiveId ? (
                        <p>{__('Vacancy ID:', 'multiposter')} {effectiveId} {!attributes.vacancyId && postType === 'vacatures' ? __('(auto-detected)', 'multiposter') : ''} — {__('Count:', 'multiposter')} {attributes.count}</p>
                    ) : (
                        <p style={{ color: '#cc0000' }}>{__('No vacancy detected. Edit a vacatures post or set a vacancy ID manually.', 'multiposter')}</p>
                    )}
                </div>
            </div>
        );
    },
    save: () => null,
});
