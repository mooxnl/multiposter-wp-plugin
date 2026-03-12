import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('multiposter/latest-vacancies', {
    title: __('Latest Vacancies', 'multiposter'),
    icon: 'star-filled',
    category: 'widgets',
    attributes: {
        count: { type: 'number', default: 3 },
        layout: { type: 'string', default: 'grid' },
    },
    edit: function Edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps();
        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Settings', 'multiposter')}>
                        <RangeControl
                            label={__('Number of vacancies', 'multiposter')}
                            value={attributes.count}
                            onChange={(val) => setAttributes({ count: val })}
                            min={1}
                            max={12}
                        />
                        <SelectControl
                            label={__('Layout', 'multiposter')}
                            value={attributes.layout}
                            options={[
                                { label: __('Grid', 'multiposter'), value: 'grid' },
                                { label: __('List', 'multiposter'), value: 'list' },
                            ]}
                            onChange={(val) => setAttributes({ layout: val })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                        <strong>{__('Latest Vacancies', 'multiposter')}</strong>
                        <p>{attributes.count} | {attributes.layout}</p>
                    </div>
                </div>
            </>
        );
    },
    save: () => null,
});
