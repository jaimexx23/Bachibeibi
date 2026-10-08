
    
  
    public function attendance(Request $request)
    {
        $access =
            $this->requireAdminOrScanner();

        if ($access) {
            return $access;
        }

        $this->touchActiveSession(
            $request
        );

        $selectedDate =
            (string)
            $request->query(
                'date',
                now()->toDateString()
            );

        $rows =
            DB::table('attendances as a')
                ->join(
                    'students as s',
                    's.student_code',
                    '=',
                    'a.student_code'
                )
                ->where(
                    'a.date',
                    $selectedDate
                )
                ->orderBy(
                    's.classroom'
                )
                ->orderBy(
                    's.full_name'
                )
                ->get([
                    's.full_name',
                    's.student_code',
                    's.classroom',
                    'a.checked_at as attendance_time',
                    'a.date as attendance_date',
                    'a.source',
                ]);

        $attendanceSections = [];
        $currentSection = null;
        $currentRecords = [];

        foreach ($rows as $row) {
            [
                $sectionGroup,
                $sectionShift
            ] = $this
                ->splitClassroomSection(
                    (string)
                    $row->classroom
                );

            $sectionLabel =
                $sectionShift === ''
                    ? $sectionGroup
                    : (
                        $sectionGroup .
                        ' - ' .
                        $sectionShift
                    );

            if (
                $currentSection !==
                $sectionLabel
            ) {
                if (
                    $currentSection !==
                    null
                ) {
                    $attendanceSections[] = [
                        'label' =>
                            $currentSection,

                        'records' =>
                            $currentRecords,
                    ];
                }

                $currentSection =
                    $sectionLabel;

                $currentRecords =
                    [];
            }

            $currentRecords[] =
                $row;
        }

        if (
            $currentSection !== null
        ) {
            $attendanceSections[] = [
                'label' =>
                    $currentSection,

                'records' =>
                    $currentRecords,
            ];
        }

        return view(
            'attendance',
            [
                'attendanceSections' =>
                    $attendanceSections,

                'selectedDate' =>
                    $selectedDate,
            ]
        );
    }
}