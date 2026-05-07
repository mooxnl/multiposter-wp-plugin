import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

registerBlockType('multiposter/vacancy-images', {
    title: __('Vacancy Image Grid', 'jobit-vacancies-for-multiposter'),
    icon: 'format-gallery',
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
        const effectiveId = attributes.vacancyId || (postType === 'multiposter_vacancy' ? postId : '');

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title={__('Settings', 'jobit-vacancies-for-multiposter')}>
                        <TextControl
                            label={__('Vacancy ID (optional override)', 'jobit-vacancies-for-multiposter')}
                            value={attributes.vacancyId}
                            onChange={(val) => setAttributes({ vacancyId: val })}
                            help={__('Leave empty to auto-detect from current post.', 'jobit-vacancies-for-multiposter')}
                        />
                    </PanelBody>
                </InspectorControls>
                <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                    <strong>{__('Vacancy Image Grid', 'jobit-vacancies-for-multiposter')}</strong>
                    {effectiveId ? (
                        <p>{__('Vacancy ID:', 'jobit-vacancies-for-multiposter')} {effectiveId} {!attributes.vacancyId && postType === 'multiposter_vacancy' ? __('(auto-detected)', 'jobit-vacancies-for-multiposter') : ''}</p>
                    ) : (
                        <p style={{ color: '#cc0000' }}>{__('No vacancy detected. Edit a vacancy post or set a vacancy ID manually.', 'jobit-vacancies-for-multiposter')}</p>
                    )}
                </div>
            </div>
        );
    },
    save: () => null,
});
