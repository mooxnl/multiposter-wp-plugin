import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('multiposter/vacancy-archive', {
    title: __('Vacancy Archive', 'multiposter'),
    icon: 'list-view',
    category: 'widgets',
    attributes: {
        postsPerPage: { type: 'number', default: 10 },
        showFilters: { type: 'boolean', default: true },
    },
    edit: function Edit({ attributes, setAttributes }) {
        const blockProps = useBlockProps();
        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Settings', 'multiposter')}>
                        <RangeControl
                            label={__('Posts per page', 'multiposter')}
                            value={attributes.postsPerPage}
                            onChange={(val) => setAttributes({ postsPerPage: val })}
                            min={1}
                            max={100}
                        />
                        <ToggleControl
                            label={__('Show filters', 'multiposter')}
                            checked={attributes.showFilters}
                            onChange={(val) => setAttributes({ showFilters: val })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                        <strong>{__('Vacancy Archive', 'multiposter')}</strong>
                        <p>{attributes.postsPerPage} {__('per page', 'multiposter')}{attributes.showFilters ? ' | ' + __('Filters enabled', 'multiposter') : ''}</p>
                    </div>
                </div>
            </>
        );
    },
    save: () => null, // Server-side rendered
});
