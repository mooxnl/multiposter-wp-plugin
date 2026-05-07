import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('multiposter/vacancy-archive', {
    title: __('Vacancy Archive', 'jobit-vacancies-for-multiposter'),
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
                    <PanelBody title={__('Settings', 'jobit-vacancies-for-multiposter')}>
                        <RangeControl
                            label={__('Posts per page', 'jobit-vacancies-for-multiposter')}
                            value={attributes.postsPerPage}
                            onChange={(val) => setAttributes({ postsPerPage: val })}
                            min={1}
                            max={100}
                        />
                        <ToggleControl
                            label={__('Show filters', 'jobit-vacancies-for-multiposter')}
                            checked={attributes.showFilters}
                            onChange={(val) => setAttributes({ showFilters: val })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div {...blockProps}>
                    <div style={{ padding: '20px', background: '#f0f0f0', textAlign: 'center' }}>
                        <strong>{__('Vacancy Archive', 'jobit-vacancies-for-multiposter')}</strong>
                        <p>{attributes.postsPerPage} {__('per page', 'jobit-vacancies-for-multiposter')}{attributes.showFilters ? ' | ' + __('Filters enabled', 'jobit-vacancies-for-multiposter') : ''}</p>
                    </div>
                </div>
            </>
        );
    },
    save: () => null, // Server-side rendered
});
