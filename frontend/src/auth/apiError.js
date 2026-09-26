export function readApiError(error) {
  const status = error?.response?.status
  const data = error?.response?.data
  const errors = data?.errors && typeof data.errors === 'object' ? data.errors : {}
  const fieldErrors = Object.fromEntries(
    Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? value[0] : String(value)]),
  )

  if (!error?.response) {
    return {
      fieldErrors: {},
      banner: 'Cannot reach the server. Start the API and try again.',
    }
  }

  if (status === 429) {
    return {
      fieldErrors: {},
      banner: 'Too many attempts. Wait a minute and try again.',
    }
  }

  if (status === 422) {
    return { fieldErrors, banner: '' }
  }

  if (status >= 500) {
    return {
      fieldErrors: {},
      banner: 'The server could not complete that request. Check that the database is running and try again.',
    }
  }

  const message = typeof data?.message === 'string' ? data.message : ''
  const safeMessage = /SQLSTATE|stack trace|vendor\\/i.test(message) ? '' : message

  return {
    fieldErrors,
    banner: safeMessage || 'Something went wrong. Try again.',
  }
}
