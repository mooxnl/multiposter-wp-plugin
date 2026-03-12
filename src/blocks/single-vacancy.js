import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('multiposter/single-vacancy', {
    title: __('Single Vacancy', 'multiposter'),
    icon: 'id',
    category: 'widgets',
    attributes: {
        vacancyId: { type: 'string', default: '' },
    },
    edit: function Edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps();
        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Settings', 'multiposter')}>
                        <TextControl
                            label={__('Vacancy ID', 'multiposter')}
                            value={attributes.vacancyId}
                            onChange={(val) => setAttributes({ vacancyId: val })}
                            help={__('Enter the post ID of the vacancy', 'multiposter')}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                        <strong>{__('Single Vacancy', 'multiposter')}</strong>
                        <p>{attributes.vacancyId ? 'ID: ' + attributes.vacancyId : __('No vacancy selected', 'multiposter')}</p>
                    </div>
                </div>
            </>
        );
    },
    save: () => null,
});
