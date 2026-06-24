export function withOptions(context, options)
{
	return Object.create(context, {
		options: {
			value: options
			, enumerable: true
			, configurable: true
		}
	});
}

export function withReportFilter(context, filter)
{
	return Object.create(context, {
		report: {
			value(descriptor) {
				if(filter(descriptor) === false)
				{
					return;
				}

				return context.report(descriptor);
			}
			, enumerable: true
			, configurable: true
		}
	});
}

export function composeListeners(...listenerMaps)
{
	const merged = {};

	for(const listenerMap of listenerMaps)
	{
		for(const [selector, handler] of Object.entries(listenerMap))
		{
			if(!merged[selector])
			{
				merged[selector] = handler;
				continue;
			}

			const previous = merged[selector];
			merged[selector] = (node) => {
				previous(node);
				handler(node);
			};
		}
	}

	return merged;
}
