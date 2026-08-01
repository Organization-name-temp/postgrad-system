import {
  type DefinedInitialDataOptions,
  type QueryKey,
  type UndefinedInitialDataOptions,
  type UseQueryOptions,
  type UseQueryResult,
  type DefinedUseQueryResult,
  useQuery,
} from '@tanstack/react-query'
import type {
  MyGrades200,
  TheStudentsTheComposerMayRegisterOnBehalfOf200,
} from '../../../shared/api/generated/endpoints'
import { customInstance } from '../../../shared/api/http'

export const reviewerGradeStudents = (signal?: AbortSignal) =>
  customInstance<TheStudentsTheComposerMayRegisterOnBehalfOf200>({
    url: '/api/v1/grading/students',
    method: 'GET',
    signal,
  })

export const getReviewerGradeStudentsQueryKey = () => ['/api/v1/grading/students'] as const

export const studentGradeRecord = (studentId: number, signal?: AbortSignal) =>
  customInstance<MyGrades200>({
    url: `/api/v1/grading/students/${studentId}`,
    method: 'GET',
    signal,
  })

export const getStudentGradeRecordQueryKey = (studentId: number) =>
  [`/api/v1/grading/students/${studentId}`] as const

export function useReviewerGradeStudents<
  TData = Awaited<ReturnType<typeof reviewerGradeStudents>>,
  TError = unknown,
>(options: {
  query: Partial<
    UseQueryOptions<Awaited<ReturnType<typeof reviewerGradeStudents>>, TError, TData, QueryKey>
  > &
    Pick<
      DefinedInitialDataOptions<
        Awaited<ReturnType<typeof reviewerGradeStudents>>,
        TError,
        Awaited<ReturnType<typeof reviewerGradeStudents>>,
        QueryKey
      >,
      'initialData'
    >
}): DefinedUseQueryResult<TData, TError>
export function useReviewerGradeStudents<
  TData = Awaited<ReturnType<typeof reviewerGradeStudents>>,
  TError = unknown,
>(options?: {
  query?: Partial<
    UseQueryOptions<Awaited<ReturnType<typeof reviewerGradeStudents>>, TError, TData, QueryKey>
  > &
    Pick<
      UndefinedInitialDataOptions<
        Awaited<ReturnType<typeof reviewerGradeStudents>>,
        TError,
        Awaited<ReturnType<typeof reviewerGradeStudents>>,
        QueryKey
      >,
      'initialData'
    >
}): UseQueryResult<TData, TError>
export function useReviewerGradeStudents<
  TData = Awaited<ReturnType<typeof reviewerGradeStudents>>,
  TError = unknown,
>(options?: {
  query?: Partial<
    UseQueryOptions<Awaited<ReturnType<typeof reviewerGradeStudents>>, TError, TData, QueryKey>
  >
}): UseQueryResult<TData, TError>
export function useReviewerGradeStudents<
  TData = Awaited<ReturnType<typeof reviewerGradeStudents>>,
  TError = unknown,
>(options?: {
  query?: Partial<
    UseQueryOptions<Awaited<ReturnType<typeof reviewerGradeStudents>>, TError, TData, QueryKey>
  >
}): UseQueryResult<TData, TError> {
  const query = options?.query

  return useQuery({
    queryKey: query?.queryKey ?? getReviewerGradeStudentsQueryKey(),
    queryFn: ({ signal }) => reviewerGradeStudents(signal),
    ...query,
  })
}

export function useStudentGradeRecord<
  TData = Awaited<ReturnType<typeof studentGradeRecord>>,
  TError = unknown,
>(
  studentId: number,
  options: {
    query: Partial<
      UseQueryOptions<Awaited<ReturnType<typeof studentGradeRecord>>, TError, TData, QueryKey>
    > &
      Pick<
        DefinedInitialDataOptions<
          Awaited<ReturnType<typeof studentGradeRecord>>,
          TError,
          Awaited<ReturnType<typeof studentGradeRecord>>,
          QueryKey
        >,
        'initialData'
      >
  },
): DefinedUseQueryResult<TData, TError>
export function useStudentGradeRecord<
  TData = Awaited<ReturnType<typeof studentGradeRecord>>,
  TError = unknown,
>(
  studentId: number,
  options?: {
    query?: Partial<
      UseQueryOptions<Awaited<ReturnType<typeof studentGradeRecord>>, TError, TData, QueryKey>
    > &
      Pick<
        UndefinedInitialDataOptions<
          Awaited<ReturnType<typeof studentGradeRecord>>,
          TError,
          Awaited<ReturnType<typeof studentGradeRecord>>,
          QueryKey
        >,
        'initialData'
      >
  },
): UseQueryResult<TData, TError>
export function useStudentGradeRecord<
  TData = Awaited<ReturnType<typeof studentGradeRecord>>,
  TError = unknown,
>(
  studentId: number,
  options?: {
    query?: Partial<
      UseQueryOptions<Awaited<ReturnType<typeof studentGradeRecord>>, TError, TData, QueryKey>
    >
  },
): UseQueryResult<TData, TError>
export function useStudentGradeRecord<
  TData = Awaited<ReturnType<typeof studentGradeRecord>>,
  TError = unknown,
>(
  studentId: number,
  options?: {
    query?: Partial<
      UseQueryOptions<Awaited<ReturnType<typeof studentGradeRecord>>, TError, TData, QueryKey>
    >
  },
): UseQueryResult<TData, TError> {
  const query = options?.query

  return useQuery({
    queryKey: query?.queryKey ?? getStudentGradeRecordQueryKey(studentId),
    queryFn: ({ signal }) => studentGradeRecord(studentId, signal),
    ...query,
  })
}
